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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/grade/grading/lib.php');

/**
 * Integration tests for grading_source: the Moodle-coupled reads (active-method gate, rubric criteria,
 * teacher filling, online-text submission, optional PII redaction). The pure reshaping is tested in
 * the engine's rubric_mapper_test; this verifies the wiring against real grading data.
 *
 * @package    assignfeedback_gradeconfidence
 * @copyright  2026 onwards
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\assignfeedback_gradeconfidence\grading_source::class)]
final class grading_source_test extends \advanced_testcase {
    /** @var \stdClass */
    private $course;
    /** @var \stdClass */
    private $teacher;
    /** @var \stdClass */
    private $student;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course();
        $this->teacher = $gen->create_and_enrol($this->course, 'editingteacher');
        // Fixed multi-character name so the PII redaction test has a redactable term.
        $this->student = $gen->create_and_enrol($this->course, 'student', ['firstname' => 'Priyanka', 'lastname' => 'Student']);
    }

    /**
     * Create an assign (online text) and return [assign, context].
     *
     * @return array{0: \assign, 1: \context_module}
     */
    private function make_assign(): array {
        $instance = $this->getDataGenerator()->create_module('assign', [
            'course' => $this->course->id,
            'assignsubmission_onlinetext_enabled' => 1,
        ]);
        $cm = get_coursemodule_from_instance('assign', $instance->id);
        $context = \context_module::instance($cm->id);
        return [new \assign($context, $cm, $this->course), $context];
    }

    public function test_returns_null_without_active_rubric(): void {
        [$assign] = $this->make_assign();
        $this->setUser($this->teacher);
        $grade = (object) ['id' => 1, 'userid' => $this->student->id];
        $this->assertNull((new grading_source($assign))->for_grade($grade));
    }

    /**
     * Build a rubric-graded submission and return the data needed to drive grading_source.
     *
     * @param \assign $assign
     * @param \context_module $context
     * @param string $text Online-text submission body.
     * @return \stdClass The assign_grades record (with ->id), graded by the teacher.
     */
    private function grade_with_rubric(\assign $assign, \context_module $context, string $text): \stdClass {
        global $DB;
        $instanceid = $assign->get_instance()->id;
        $this->setUser($this->teacher);

        // Rubric definition: two criteria, levels with scores 0..3.
        $rubricgen = $this->getDataGenerator()->get_plugin_generator('gradingform_rubric');
        $criteria = [
            'Thesis' => ['Absent' => 0, 'Unclear' => 1, 'Clear position' => 2, 'Nuanced' => 3],
            'Evidence' => ['None' => 0, 'Asserts' => 1, 'Some support' => 2, 'Cited' => 3],
        ];
        $controller = $rubricgen->create_instance($context, 'mod_assign', 'submissions', 'r', 'd', $criteria);
        get_grading_manager($context, 'mod_assign', 'submissions')->set_active_method('rubric');

        // Online-text submission for the student.
        $submission = $DB->insert_record('assign_submission', (object) [
            'assignment' => $instanceid, 'userid' => $this->student->id, 'status' => 'submitted',
            'attemptnumber' => 0, 'latest' => 1, 'timecreated' => 1, 'timemodified' => 1,
        ]);
        $DB->insert_record('assignsubmission_onlinetext', (object) [
            'assignment' => $instanceid, 'submission' => $submission,
            'onlinetext' => '<p>' . $text . '</p>', 'onlineformat' => FORMAT_HTML,
        ]);

        // An assign_grades row, then a rubric filling for it by the teacher.
        $grade = (object) [
            'assignment' => $instanceid, 'userid' => $this->student->id, 'grader' => $this->teacher->id,
            'grade' => -1, 'attemptnumber' => 0, 'timecreated' => 1, 'timemodified' => 1,
        ];
        $grade->id = $DB->insert_record('assign_grades', $grade);

        // Teacher picks: Thesis -> score 0 (Absent, ordinal 0), Evidence -> score 2 (Some support, ordinal 2).
        // get_submitted_form_data builds the exact form structure update() expects (matched by description).
        $data = $rubricgen->get_submitted_form_data($controller, $grade->id, [
            'Thesis' => ['score' => 0, 'remark' => ''],
            'Evidence' => ['score' => 2, 'remark' => ''],
        ]);
        // Persisting via submit_and_get_grade() also transitions the instance to ACTIVE (what
        // mod_assign does on save); a plain update() would leave it INCOMPLETE and unreadable.
        $instance = $controller->create_instance($this->teacher->id, $grade->id);
        $instance->submit_and_get_grade($data, $grade->id);

        return $grade;
    }

    public function test_returns_payload_for_rubric_graded_submission(): void {
        [$assign, $context] = $this->make_assign();
        $grade = $this->grade_with_rubric($assign, $context, 'A short essay body about the topic.');

        $this->setUser($this->teacher);
        $payload = (new grading_source($assign))->for_grade($grade);

        $this->assertNotNull($payload);
        // Rubric: two criteria.
        $this->assertCount(2, $payload['rubric']);
        $names = array_column($payload['rubric'], 'name');
        $this->assertContains('Thesis', $names);
        $this->assertContains('Evidence', $names);
        // Teacher selections mapped to ordinals (Thesis=0, Evidence=2).
        $byid = [];
        foreach ($payload['rubric'] as $c) {
            $byid[$c['name']] = $c['id'];
        }
        $this->assertSame(0, $payload['teacher'][$byid['Thesis']]);
        $this->assertSame(2, $payload['teacher'][$byid['Evidence']]);
        // Submission text is plain (HTML stripped).
        $this->assertStringContainsString('A short essay body about the topic.', $payload['submission']);
        $this->assertStringNotContainsString('<p>', $payload['submission']);
    }

    public function test_returns_null_when_no_submission(): void {
        global $DB;
        [$assign, $context] = $this->make_assign();
        $grade = $this->grade_with_rubric($assign, $context, 'temp');
        // Remove the submission text so there is nothing to review.
        $DB->delete_records('assignsubmission_onlinetext');
        $DB->delete_records('assign_submission');

        $this->setUser($this->teacher);
        $this->assertNull((new grading_source($assign))->for_grade($grade));
    }

    public function test_redacts_student_name_when_enabled(): void {
        [$assign, $context] = $this->make_assign();
        $name = $this->student->firstname;
        $grade = $this->grade_with_rubric($assign, $context, "An essay written by {$name} about evidence.");

        set_config('redactpii', 1, 'aiplacement_gradeconfidence');
        $this->setUser($this->teacher);
        $payload = (new grading_source($assign))->for_grade($grade);

        $this->assertStringNotContainsString($name, $payload['submission']);
    }

    /**
     * Store a file against the student's submission for this assignment.
     *
     * @param \assign $assign
     * @param \context_module $context
     * @param string $filename
     * @param string $content
     */
    private function add_file(\assign $assign, \context_module $context, string $filename, string $content): void {
        $submission = $assign->get_user_submission((int) $this->student->id, false);
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'assignsubmission_file',
            'filearea' => 'submission_files',
            'itemid' => (int) $submission->id,
            'filepath' => '/',
            'filename' => $filename,
        ], $content);
    }

    public function test_reads_text_file_attachment_alongside_online_text(): void {
        [$assign, $context] = $this->make_assign();
        $grade = $this->grade_with_rubric($assign, $context, 'Online part of the essay.');
        $this->add_file($assign, $context, 'essay.txt', 'Attached part about the evidence.');

        $this->setUser($this->teacher);
        $payload = (new grading_source($assign))->for_grade($grade);

        $this->assertStringContainsString('Online part of the essay.', $payload['submission']);
        $this->assertStringContainsString('Attached part about the evidence.', $payload['submission']);
    }

    public function test_library_rubric_fallback_when_no_native_rubric(): void {
        global $DB;
        [$assign, $context] = $this->make_assign();
        $instanceid = (int) $assign->get_instance()->id;
        $this->setUser($this->teacher);

        // A library rubric, configured on this activity's feedback plugin.
        $rid = \aiplacement_gradeconfidence\local\rubric_library::save(
            0,
            (int) $this->teacher->id,
            'Lib',
            [['name' => 'Idea', 'levels' => ['Weak', 'OK', 'Strong']]]
        );
        $assign->get_feedback_plugin_by_type('gradeconfidence')->set_config('libraryrubric', $rid);

        // Online-text submission + a grade row (no native rubric, no filling).
        $sub = $DB->insert_record('assign_submission', (object) [
            'assignment' => $instanceid, 'userid' => $this->student->id, 'status' => 'submitted',
            'attemptnumber' => 0, 'latest' => 1, 'timecreated' => 1, 'timemodified' => 1,
        ]);
        $DB->insert_record('assignsubmission_onlinetext', (object) [
            'assignment' => $instanceid, 'submission' => $sub,
            'onlinetext' => '<p>An essay about the idea.</p>', 'onlineformat' => FORMAT_HTML,
        ]);
        $grade = (object) [
            'assignment' => $instanceid, 'userid' => $this->student->id, 'grader' => $this->teacher->id,
            'grade' => -1, 'attemptnumber' => 0, 'timecreated' => 1, 'timemodified' => 1,
        ];
        $grade->id = $DB->insert_record('assign_grades', $grade);

        $payload = (new grading_source($assign))->for_grade($grade);
        $this->assertNotNull($payload);
        $this->assertSame('Idea', $payload['rubric'][0]['name']);
        // No teacher filling — it's an AI assessment, not a diff.
        $this->assertSame([], $payload['teacher']);
        $this->assertStringContainsString('An essay about the idea.', $payload['submission']);
    }

    public function test_unreadable_file_degrades_gracefully(): void {
        [$assign, $context] = $this->make_assign();
        $grade = $this->grade_with_rubric($assign, $context, 'Online only.');
        // Genuinely binary content (so it is not sniffed as text) with no converter path → unreadable.
        $this->add_file($assign, $context, 'scan.dat', "\x00\x01\x02SECRETBIN\xff\xfe");

        $this->setUser($this->teacher);
        $payload = (new grading_source($assign))->for_grade($grade);

        $this->assertStringContainsString('Online only.', $payload['submission']);
        $this->assertStringNotContainsString('SECRETBIN', $payload['submission']);
    }
}
