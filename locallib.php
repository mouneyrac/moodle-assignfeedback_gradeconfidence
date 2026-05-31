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

/**
 * Grade Confidence assignment feedback plugin.
 *
 * @package    assignfeedback_gradeconfidence
 * @copyright  2026 onwards
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * The assign feedback plugin class (legacy global class name required by mod_assign).
 *
 * Auto-review on save (UX §7.2): when the teacher saves a grade, review the rubric selections for
 * consistency, store the outcome, and send a notification only on a material discrepancy.
 *
 * @package    assignfeedback_gradeconfidence
 * @copyright  2026 onwards
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_feedback_gradeconfidence extends assign_feedback_plugin {
    #[\Override]
    public function get_name() {
        return get_string('pluginname', 'assignfeedback_gradeconfidence');
    }

    #[\Override]
    public function get_settings(\MoodleQuickForm $mform) {
        global $USER;
        // Let the teacher pick one of their library rubrics to use when this activity has no native rubric.
        $options = [0 => get_string('libraryrubricnone', 'assignfeedback_gradeconfidence')];
        foreach (\aiplacement_gradeconfidence\local\rubric_library::list_for_owner((int) $USER->id) as $rubric) {
            $options[(int) $rubric->id] = format_string($rubric->name);
        }
        $mform->addElement(
            'select',
            'assignfeedback_gradeconfidence_libraryrubric',
            get_string('libraryrubric', 'assignfeedback_gradeconfidence'),
            $options
        );
        $mform->setDefault('assignfeedback_gradeconfidence_libraryrubric', (int) $this->get_config('libraryrubric'));
        $mform->hideIf(
            'assignfeedback_gradeconfidence_libraryrubric',
            'assignfeedback_gradeconfidence_enabled',
            'notchecked'
        );

        // Optional model answer / exemplar — used by the AI as a reference for the standard (not a key).
        $mform->addElement(
            'textarea',
            'assignfeedback_gradeconfidence_exemplar',
            get_string('exemplar', 'assignfeedback_gradeconfidence'),
            ['rows' => 5, 'cols' => 60]
        );
        $mform->setType('assignfeedback_gradeconfidence_exemplar', PARAM_TEXT);
        $mform->setDefault('assignfeedback_gradeconfidence_exemplar', (string) $this->get_config('exemplar'));
        $mform->hideIf(
            'assignfeedback_gradeconfidence_exemplar',
            'assignfeedback_gradeconfidence_enabled',
            'notchecked'
        );
    }

    #[\Override]
    public function save_settings(stdClass $data) {
        $rubrickey = 'assignfeedback_gradeconfidence_libraryrubric';
        $this->set_config('libraryrubric', isset($data->$rubrickey) ? (int) $data->$rubrickey : 0);
        $exemplarkey = 'assignfeedback_gradeconfidence_exemplar';
        $this->set_config('exemplar', isset($data->$exemplarkey) ? (string) $data->$exemplarkey : '');
        return true;
    }

    /**
     * We have no editable feedback fields, but we return true so mod_assign calls save() on every grade
     * save — that is our auto-review trigger. (Side effect: marks the grade modified; acceptable for v0.1.)
     *
     * @param stdClass $grade
     * @param stdClass $data
     * @return bool
     */
    #[\Override]
    public function is_feedback_modified(stdClass $grade, stdClass $data) {
        return true;
    }

    /**
     * Optional prompt directives from site config + this activity's model answer.
     *
     * @return array{language: string, fairness: bool, exemplar: string}
     */
    private function prompt_options(): array {
        return [
            'language' => (string) get_config('aiplacement_gradeconfidence', 'feedbacklanguage'),
            'fairness' => (bool) get_config('aiplacement_gradeconfidence', 'fairmode'),
            'exemplar' => (string) $this->get_config('exemplar'),
            'detect' => (bool) get_config('aiplacement_gradeconfidence', 'aidetection'),
        ];
    }

    /**
     * The configured review mode (default on-demand — the teacher triggers checks).
     *
     * @return string One of 'auto', 'manual', 'off'.
     */
    private function current_mode(): string {
        $mode = get_config('aiplacement_gradeconfidence', 'mode');
        return ($mode === false || $mode === '') ? 'manual' : (string) $mode;
    }

    #[\Override]
    public function save(stdClass $grade, stdClass $data) {
        // Only automatic mode reviews on save; manual/off do not.
        if ($this->current_mode() !== 'auto') {
            return true;
        }
        if (!has_capability('aiplacement/gradeconfidence:review', $this->assignment->get_context())) {
            return true;
        }
        try {
            $this->review_grade($grade);
        } catch (\Throwable $e) {
            // Never block the teacher's grade save because the assistant failed.
            debugging('assignfeedback_gradeconfidence review failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        return true;
    }

    /**
     * Review one grade against the rubric, store the outcome, and notify on a material discrepancy.
     *
     * @param stdClass $grade An assign_grades record.
     * @return array|null The grader result, or null if there was nothing to review.
     */
    private function review_grade(stdClass $grade): ?array {
        global $USER;
        $context = $this->assignment->get_context();
        $payload = (new \assignfeedback_gradeconfidence\grading_source($this->assignment))->for_grade($grade);
        if ($payload === null) {
            return null;
        }

        $result = (new \aiplacement_gradeconfidence\grader())->review(
            $context->id,
            (int) $USER->id,
            $payload['rubric'],
            $payload['submission'],
            $payload['teacher'],
            $this->prompt_options(),
        );

        \assignfeedback_gradeconfidence\storage::save_review(
            (int) $context->id,
            (int) $grade->id,
            (int) $grade->userid,
            $result,
        );

        // Assessment mode (a library rubric with no teacher filling) is informational — never notify.
        if ($result['alert'] === \aiplacement_gradeconfidence\local\reviewer::ALERT_NOTIFY && $payload['teacher'] !== []) {
            $url = new \moodle_url('/mod/assign/view.php', [
                'id' => $this->assignment->get_course_module()->id,
                'action' => 'grader',
                'userid' => (int) $grade->userid,
            ]);
            \assignfeedback_gradeconfidence\notifier::flag(
                (int) $USER->id,
                fullname(\core_user::get_user((int) $grade->userid)),
                format_string($this->assignment->get_instance()->name),
                $url,
                $result['flags'],
            );
        }
        return $result;
    }

    #[\Override]
    public function get_grading_actions() {
        // On-demand trigger, only in manual mode and only for graders.
        if ($this->current_mode() !== 'manual') {
            return [];
        }
        if (!has_capability('aiplacement/gradeconfidence:review', $this->assignment->get_context())) {
            return [];
        }
        return ['review' => get_string('manualreview', 'assignfeedback_gradeconfidence')];
    }

    #[\Override]
    public function view_page($action) {
        if ($action === 'reviewone') {
            return $this->run_single_review();
        }
        if ($action !== 'review') {
            return '';
        }
        global $OUTPUT;
        $context = $this->assignment->get_context();
        require_capability('aiplacement/gradeconfidence:review', $context);

        $cmid = $this->assignment->get_course_module()->id;
        $backurl = new \moodle_url('/mod/assign/view.php', ['id' => $cmid, 'action' => 'grading']);

        // Side-effecting (calls the AI + writes rows): require an explicit, sesskey-protected confirmation.
        if (optional_param('confirm', 0, PARAM_BOOL) && confirm_sesskey()) {
            $tally = $this->run_manual_batch();
            $summary = get_string('manualdone', 'assignfeedback_gradeconfidence', (object) $tally);
            return $OUTPUT->notification($summary, 'info', false)
                . $OUTPUT->continue_button($backurl);
        }

        $count = $this->count_reviewable_grades();
        $confirmurl = new \moodle_url('/mod/assign/view.php', [
            'id' => $cmid,
            'action' => 'viewpluginpage',
            'pluginsubtype' => 'assignfeedback',
            'plugin' => 'gradeconfidence',
            'pluginaction' => 'review',
            'confirm' => 1,
            'sesskey' => sesskey(),
        ]);
        return $OUTPUT->confirm(
            get_string('manualconfirm', 'assignfeedback_gradeconfidence', $count),
            $confirmurl,
            $backurl
        );
    }

    /**
     * Run an on-demand review for a single graded submission (the per-student button), then return to the
     * grading list. Side-effecting (calls the AI + writes rows), so it is capability- and sesskey-gated.
     *
     * @return string
     */
    private function run_single_review(): string {
        global $USER;
        $context = $this->assignment->get_context();
        require_capability('aiplacement/gradeconfidence:review', $context);
        require_sesskey();
        $userid = required_param('reviewuserid', PARAM_INT);
        $backurl = new \moodle_url('/mod/assign/view.php', [
            'id' => $this->assignment->get_course_module()->id,
            'action' => 'grading',
        ]);

        // Per-teacher credit gate: stop before spending a check once the allowance is used up.
        $guard = new \aiplacement_gradeconfidence\local\credit_guard();
        $status = $guard->status((int) $context->id, (int) $USER->id);
        if ($status['enabled'] && !$status['can']) {
            $requesturl = new \moodle_url('/ai/placement/gradeconfidence/creditrequest.php', [
                'courseid' => (int) $this->assignment->get_course_module()->course,
                'sesskey' => sesskey(),
            ]);
            redirect($backurl, get_string('creditsout', 'assignfeedback_gradeconfidence') . ' '
                . \html_writer::link($requesturl, get_string('requestmore', 'assignfeedback_gradeconfidence')));
        }

        $grade = $this->assignment->get_user_grade($userid, false);
        $message = get_string('reviewnograde', 'assignfeedback_gradeconfidence');
        if ($grade) {
            try {
                $this->review_grade($grade);
                $guard->consume((int) $context->id, (int) $USER->id);
                $message = get_string('reviewdone', 'assignfeedback_gradeconfidence');
            } catch (\Throwable $e) {
                debugging('on-demand review failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
                $message = get_string('reviewfailed', 'assignfeedback_gradeconfidence');
            }
        }
        redirect($backurl, $message);
    }

    /**
     * The per-student "ask for a check now" button (on-demand mode only), pointing at the reviewone action.
     *
     * @param stdClass $grade An assign_grades record.
     * @return string
     */
    private function request_button(stdClass $grade): string {
        global $USER;
        $guard = new \aiplacement_gradeconfidence\local\credit_guard();
        $status = $guard->status((int) $this->assignment->get_context()->id, (int) $USER->id);
        if ($status['enabled'] && !$status['can']) {
            // Allowance used up for this course: a quiet note plus a one-click "ask for more" link.
            $requesturl = new \moodle_url('/ai/placement/gradeconfidence/creditrequest.php', [
                'courseid' => (int) $this->assignment->get_course_module()->course,
                'sesskey' => sesskey(),
            ]);
            return \html_writer::div(
                get_string('creditsoutshort', 'assignfeedback_gradeconfidence') . ' '
                    . \html_writer::link($requesturl, get_string('requestmore', 'assignfeedback_gradeconfidence')),
                'gradeconfidence-credits text-muted'
            );
        }
        $url = new \moodle_url('/mod/assign/view.php', [
            'id' => $this->assignment->get_course_module()->id,
            'action' => 'viewpluginpage',
            'pluginsubtype' => 'assignfeedback',
            'plugin' => 'gradeconfidence',
            'pluginaction' => 'reviewone',
            'reviewuserid' => (int) $grade->userid,
            'sesskey' => sesskey(),
        ]);
        $button = \html_writer::link(
            $url,
            get_string('requestcheck', 'assignfeedback_gradeconfidence'),
            ['class' => 'btn btn-secondary btn-sm']
        );
        if ($status['enabled']) {
            $button .= ' ' . \html_writer::span(
                get_string('creditsleft', 'assignfeedback_gradeconfidence', $status['remaining']),
                'gradeconfidence-credits text-muted'
            );
        }
        return \html_writer::div($button, 'gradeconfidence-request');
    }

    /**
     * Graded submissions in this assignment (the candidates for a manual review).
     *
     * @return array assign_grades records.
     */
    private function reviewable_grades(): array {
        global $DB;
        return $DB->get_records('assign_grades', ['assignment' => (int) $this->assignment->get_instance()->id]);
    }

    /**
     * Count the graded submissions available to review.
     *
     * @return int
     */
    private function count_reviewable_grades(): int {
        return count($this->reviewable_grades());
    }

    /**
     * Run a review for every graded submission in this assignment.
     *
     * @return array{reviewed: int, flagged: int, skipped: int}
     */
    private function run_manual_batch(): array {
        $reviewed = 0;
        $flagged = 0;
        $skipped = 0;
        foreach ($this->reviewable_grades() as $grade) {
            try {
                $result = $this->review_grade($grade);
            } catch (\Throwable $e) {
                $result = null;
            }
            if ($result === null) {
                $skipped++;
                continue;
            }
            $reviewed++;
            if ($result['alert'] === \aiplacement_gradeconfidence\local\reviewer::ALERT_NOTIFY) {
                $flagged++;
            }
        }
        return ['reviewed' => $reviewed, 'flagged' => $flagged, 'skipped' => $skipped];
    }

    /**
     * Whether the current viewer is a grader (teacher) rather than the graded student.
     *
     * The internal assurance QA (flags, AI-vs-teacher levels, evidence quotes) is for graders only.
     * Students see a neutral "reviewed for consistency" signal instead (Article 13 disclosure).
     *
     * @return bool
     */
    private function viewer_can_grade(): bool {
        return has_capability('mod/assign:grade', $this->assignment->get_context());
    }

    #[\Override]
    public function is_empty(stdClass $grade) {
        return \assignfeedback_gradeconfidence\storage::get_review((int) $grade->id) === null;
    }

    /**
     * Show the grader their remaining check allowance inside the grade form — informational only.
     *
     * The actual on-demand trigger stays in the submissions list, because a check needs the *saved* grade
     * (the in-form value is not persisted yet). This static line just surfaces "how many checks do I have
     * left" where the teacher is grading. Shown only to graders, in on-demand mode, when credits are on.
     *
     * @param mixed $grade The grade data (unused; the allowance is per-teacher, not per-student).
     * @param MoodleQuickForm $mform The grade form.
     * @param stdClass $data The form data.
     * @param int $userid The user being graded (unused, same reason).
     * @return bool True if an element was added.
     */
    #[\Override]
    public function get_form_elements_for_user($grade, MoodleQuickForm $mform, stdClass $data, $userid) {
        global $USER;
        if (!$this->viewer_can_grade() || $this->current_mode() !== 'manual') {
            return false;
        }
        $guard = new \aiplacement_gradeconfidence\local\credit_guard();
        $status = $guard->status((int) $this->assignment->get_context()->id, (int) $USER->id);
        if (!$status['enabled']) {
            return false;
        }
        $mform->addElement(
            'static',
            'assignfeedback_gradeconfidence_credits',
            get_string('pluginname', 'assignfeedback_gradeconfidence'),
            get_string('creditstatus', 'assignfeedback_gradeconfidence', $status['remaining'])
        );
        return true;
    }

    #[\Override]
    public function view_summary(stdClass $grade, &$showviewlink) {
        $showviewlink = false;
        $review = \assignfeedback_gradeconfidence\storage::get_review((int) $grade->id);
        if (!$review) {
            // No review yet: in on-demand mode, offer the grader a per-student check button.
            if ($this->viewer_can_grade() && $this->current_mode() === 'manual') {
                return $this->request_button($grade);
            }
            return '';
        }

        // Student-facing: only a neutral assurance signal, and only if a review actually completed.
        if (!$this->viewer_can_grade()) {
            return $review['status'] === 'ok' ? $this->student_signal() : '';
        }

        if ($review['status'] !== 'ok') {
            return $this->partial_message($review['status']);
        }
        if ($this->is_low_confidence($review)) {
            return get_string('reviewlowconfidenceshort', 'assignfeedback_gradeconfidence');
        }
        if ($this->is_assessment($review['flags'])) {
            $showviewlink = true;
            return get_string('assessmentsummary', 'assignfeedback_gradeconfidence', count($review['flags']));
        }
        if ($review['alert'] === \aiplacement_gradeconfidence\local\reviewer::ALERT_CONSISTENT) {
            return '✓ ' . get_string('reviewconsistent', 'assignfeedback_gradeconfidence');
        }
        $showviewlink = true;
        return '⚠ ' . get_string('reviewflags', 'assignfeedback_gradeconfidence', count($review['flags']));
    }

    /**
     * The student-facing assurance signal + link to the explainer (Article 13 / Recital 27 disclosure).
     *
     * @return string
     */
    private function student_signal(): string {
        $link = \html_writer::link(
            new \moodle_url('/ai/placement/gradeconfidence/explain.php'),
            get_string('studentexplain', 'assignfeedback_gradeconfidence')
        );
        return \html_writer::div(
            '✓ ' . get_string('studentreviewed', 'assignfeedback_gradeconfidence') . ' ' . $link,
            'gradeconfidence-student',
            ['role' => 'status']
        );
    }

    /**
     * Teacher-facing message for a review that did not complete.
     *
     * @param string $status The stored review status.
     * @return string
     */
    private function partial_message(string $status): string {
        return match ($status) {
            'toolong' => get_string('reviewtoolong', 'assignfeedback_gradeconfidence'),
            'budgetexceeded' => get_string('reviewbudget', 'assignfeedback_gradeconfidence'),
            default => get_string('reviewpartial', 'assignfeedback_gradeconfidence'),
        };
    }

    #[\Override]
    public function view(stdClass $grade) {
        $review = \assignfeedback_gradeconfidence\storage::get_review((int) $grade->id);
        if (!$review) {
            // No review yet: in on-demand mode, offer the grader a per-student check button.
            if ($this->viewer_can_grade() && $this->current_mode() === 'manual') {
                return $this->request_button($grade);
            }
            return '';
        }
        // Student-facing: only the neutral assurance signal, never the internal flags/quotes.
        if (!$this->viewer_can_grade()) {
            return $review['status'] === 'ok' ? $this->student_signal() : '';
        }
        if ($review['status'] !== 'ok') {
            return $this->partial_message($review['status']);
        }
        if ($this->is_low_confidence($review)) {
            // Withhold a review the samples disagreed on rather than show flags the AI was unsure about.
            return $this->panel(\html_writer::div(
                get_string('reviewlowconfidence', 'assignfeedback_gradeconfidence'),
                'gradeconfidence-lowconf',
                ['role' => 'status']
            ));
        }
        // Decorative icon: hidden from screen readers (the adjacent text carries the meaning, so the
        // state is never conveyed by colour or icon alone).
        $icon = fn (string $glyph): string => \html_writer::span($glyph . ' ', 'gradeconfidence-icon', ['aria-hidden' => 'true']);

        // Advisory AI-likelihood (teacher-only, never affects the grade). Appended to every panel.
        $advisory = $this->detection_advisory($review);

        if ($review['alert'] === \aiplacement_gradeconfidence\local\reviewer::ALERT_CONSISTENT) {
            return \html_writer::div(
                $icon('✓') . get_string('reviewconsistent', 'assignfeedback_gradeconfidence'),
                'gradeconfidence-consistent',
                ['role' => 'status']
            ) . $advisory;
        }

        $list = \html_writer::tag('ul', $this->flag_items($review['flags']), ['class' => 'gradeconfidence-flags']);
        $trace = $this->trace_link($review);

        // Assessment mode (no teacher filling): the AI's read of each criterion — the teacher still grades.
        if ($this->is_assessment($review['flags'])) {
            $heading = \html_writer::tag(
                'p',
                $icon('🛈') . get_string('assessmentheading', 'assignfeedback_gradeconfidence'),
                ['class' => 'gradeconfidence-flags-heading']
            );
            return $this->panel($heading . $list . $trace . $advisory);
        }

        $heading = \html_writer::tag(
            'p',
            $icon('⚠')
            . get_string('reviewflags', 'assignfeedback_gradeconfidence', count($review['flags'])),
            ['class' => 'gradeconfidence-flags-heading']
        );
        return $this->panel($heading . $list . $trace . $advisory);
    }

    /**
     * The teacher-only advisory AI-likelihood line (empty unless a likelihood was recorded). It is
     * explicitly framed as fallible and never affects the grade; it is never reached for students.
     *
     * @param array $review
     * @return string
     */
    private function detection_advisory(array $review): string {
        $level = $review['detection'] ?? null;
        if (!in_array($level, ['low', 'medium', 'high'], true)) {
            return '';
        }
        return \html_writer::div(
            get_string('aidetection_' . $level, 'assignfeedback_gradeconfidence')
            . ' ' . get_string('aidetectioncaveat', 'assignfeedback_gradeconfidence'),
            'gradeconfidence-advisory',
            ['role' => 'note']
        );
    }

    /**
     * Whether a review is an AI assessment (a library rubric with no teacher filling to diff against).
     *
     * @param array $flags
     * @return bool
     */
    private function is_assessment(array $flags): bool {
        if ($flags === []) {
            return false;
        }
        foreach ($flags as $f) {
            if (($f['teacherlevel'] ?? null) !== null) {
                return false;
            }
        }
        return true;
    }

    /**
     * Whether a review should be withheld because the independent samples disagreed too much (below the
     * site's minimum-confidence threshold). We would rather show nothing than a review we do not trust.
     *
     * @param array $review The stored review.
     * @return bool
     */
    private function is_low_confidence(array $review): bool {
        $confidence = $review['confidence'] ?? null;
        $min = (int) get_config('aiplacement_gradeconfidence', 'minconfidence');
        return $confidence !== null && $min > 0 && $confidence < $min;
    }

    /**
     * Render the per-criterion list items shared by the flags panel and the assessment panel.
     *
     * @param array $flags
     * @return string
     */
    private function flag_items(array $flags): string {
        $lis = '';
        foreach ($flags as $f) {
            $line = \html_writer::tag('strong', s($f['name'] ?? ''));
            $line .= ' — ' . get_string('aisuggests', 'assignfeedback_gradeconfidence', (object) [
                'ai' => (int) ($f['ailevel'] ?? 0),
                'ailabel' => s($f['ailabel'] ?? ''),
            ]);
            if (!empty($f['reasoning'])) {
                $line .= \html_writer::tag('p', s($f['reasoning']), ['class' => 'gradeconfidence-reason']);
            }
            foreach (($f['evidence'] ?? []) as $q) {
                if (!empty($q['verified'])) {
                    $line .= \html_writer::tag('blockquote', s($q['text']), ['class' => 'gradeconfidence-quote']);
                }
            }
            $lis .= \html_writer::tag('li', $line, ['class' => 'gradeconfidence-flag']);
        }
        return $lis;
    }

    /**
     * The "view full trace" link for a review (empty string if no run id).
     *
     * @param array $review
     * @return string
     */
    private function trace_link(array $review): string {
        if (empty($review['runid'])) {
            return '';
        }
        return \html_writer::div(\html_writer::link(
            new \moodle_url('/ai/placement/gradeconfidence/trace.php', ['run' => (int) $review['runid']]),
            get_string('viewtrace', 'assignfeedback_gradeconfidence')
        ), 'gradeconfidence-trace-link');
    }

    /**
     * Wrap panel content in the accessible region.
     *
     * @param string $inner
     * @return string
     */
    private function panel(string $inner): string {
        return \html_writer::tag('section', $inner, [
            'class' => 'gradeconfidence-panel',
            'role' => 'region',
            'aria-label' => get_string('pluginname', 'assignfeedback_gradeconfidence'),
        ]);
    }
}
