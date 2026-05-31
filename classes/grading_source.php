<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace assignfeedback_gradeconfidence;

use aiplacement_gradeconfidence\local\rubric_mapper;
use aiplacement_gradeconfidence\local\submission_assembler;

/**
 * Fetches the live rubric definition, the teacher's filling, and the submission text for one grade, and
 * reshapes them (via the pure rubric_mapper) into the grader's inputs. This is the Moodle-coupled edge;
 * the reshaping itself is tested separately.
 *
 * v0.1 supports rubric advanced grading + online-text submissions. Returns null when there's nothing to
 * review (no active rubric, no submission), so the caller simply skips.
 *
 * @package    assignfeedback_gradeconfidence
 * @copyright  2026 onwards
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grading_source {
    /**
     * Construct the grading source for one assignment.
     *
     * @param \assign $assign The assignment.
     */
    public function __construct(
        /** @var \assign The assignment. */
        private \assign $assign,
    ) {
    }

    /**
     * Build the review payload for one grade.
     *
     * @param \stdClass $grade An assign_grades record.
     * @return array{rubric: array, teacher: array, submission: string}|null
     */
    public function for_grade(\stdClass $grade): ?array {
        $resolved = $this->resolve_rubric($grade);
        if ($resolved === null) {
            return null;
        }

        $submission = $this->submission_text((int) $grade->userid);
        if ($submission === '') {
            return null;
        }

        // Optional best-effort PII redaction before the text leaves the site (engine setting).
        if (get_config('aiplacement_gradeconfidence', 'redactpii')) {
            $student = \core_user::get_user((int) $grade->userid, 'firstname, lastname');
            $terms = $student ? [$student->firstname, $student->lastname] : [];
            $submission = \aiplacement_gradeconfidence\local\redactor::redact($submission, $terms);
        }

        return ['rubric' => $resolved['rubric'], 'teacher' => $resolved['teacher'], 'submission' => $submission];
    }

    /**
     * Resolve the rubric + teacher selections: the native advanced-grading rubric if present, otherwise a
     * library rubric configured on the activity (with no teacher filling — an AI assessment, not a diff).
     *
     * @param \stdClass $grade
     * @return array{rubric: array, teacher: array}|null
     */
    private function resolve_rubric(\stdClass $grade): ?array {
        global $USER;
        $manager = get_grading_manager($this->assign->get_context(), 'mod_assign', 'submissions');
        if ($manager->get_active_method() === 'rubric') {
            $controller = $manager->get_controller('rubric');
            if ($controller->is_form_available()) {
                $criteria = $controller->get_definition()->rubric_criteria ?? [];
                if ($criteria) {
                    $instance = $controller->get_current_instance($USER->id, $grade->id);
                    $filling = $instance ? ($instance->get_rubric_filling()['criteria'] ?? []) : [];
                    return [
                        'rubric' => rubric_mapper::map_rubric($criteria),
                        'teacher' => rubric_mapper::map_teacher($criteria, $filling),
                    ];
                }
            }
        }

        // Fallback: a library rubric chosen for this activity. There is no teacher filling to compare to,
        // so the review becomes an AI assessment of each criterion.
        $libraryid = $this->library_rubric_id();
        if ($libraryid) {
            $rubric = \aiplacement_gradeconfidence\local\rubric_library::get($libraryid);
            if ($rubric) {
                return [
                    'rubric' => \aiplacement_gradeconfidence\local\rubric_text::to_review_rubric(
                        \aiplacement_gradeconfidence\local\rubric_library::definition($rubric)
                    ),
                    'teacher' => [],
                ];
            }
        }
        return null;
    }

    /**
     * The library rubric id configured on this activity (0 if none).
     *
     * @return int
     */
    private function library_rubric_id(): int {
        $plugin = $this->assign->get_feedback_plugin_by_type('gradeconfidence');
        return $plugin ? (int) $plugin->get_config('libraryrubric') : 0;
    }

    /**
     * The student's submission as plain text: online text plus any text we can extract from file
     * attachments (text-like files read directly; PDF/DOCX via core's converter, best-effort).
     *
     * @param int $userid
     * @return string Empty string if nothing readable.
     */
    private function submission_text(int $userid): string {
        $submission = $this->assign->get_user_submission($userid, false);
        if (!$submission) {
            return '';
        }
        $assembled = submission_assembler::assemble(
            $this->online_text((int) $submission->id),
            $this->file_texts((int) $submission->id)
        );
        return $assembled['text'];
    }

    /**
     * Plain text of the online-text submission.
     *
     * @param int $submissionid
     * @return string
     */
    private function online_text(int $submissionid): string {
        global $DB;
        $record = $DB->get_record('assignsubmission_onlinetext', ['submission' => $submissionid]);
        if (!$record || $record->onlinetext === null) {
            return '';
        }
        // The AI wants plain text; strip the editor HTML.
        return trim(html_to_text((string) $record->onlinetext, 0, false));
    }

    /**
     * Extract text from each attached file submission.
     *
     * @param int $submissionid
     * @return array List of ['name' => string, 'text' => string|null] (null = could not be read).
     */
    private function file_texts(int $submissionid): array {
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $this->assign->get_context()->id,
            'assignsubmission_file',
            'submission_files',
            $submissionid,
            'filename',
            false
        );
        $out = [];
        foreach ($files as $file) {
            $out[] = ['name' => $file->get_filename(), 'text' => self::extract_text($file)];
        }
        return $out;
    }

    /**
     * Best-effort plain-text extraction for one file.
     *
     * @param \stored_file $file
     * @return string|null Null if the file type can't be read with what's configured.
     */
    private static function extract_text(\stored_file $file): ?string {
        $mime = (string) $file->get_mimetype();
        if (str_starts_with($mime, 'text/')) {
            $content = (string) $file->get_content();
            return $mime === 'text/html' ? content_to_text($content, FORMAT_HTML) : $content;
        }
        // Non-text (PDF/DOCX/...): try core's document converter to plain text, degrade if unavailable.
        try {
            $ext = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
            $converter = new \core_files\converter();
            if (!$converter->can_convert_format_to($ext, 'txt')) {
                return null;
            }
            $conversion = $converter->start_conversion($file, 'txt');
            if ($conversion->get('status') !== \core_files\conversion::STATUS_COMPLETE) {
                return null; // Async / not ready — review what we have rather than blocking.
            }
            $dest = $conversion->get_destfile();
            return $dest ? (string) $dest->get_content() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
