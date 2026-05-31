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

/**
 * Tests for the assign_feedback_gradeconfidence plugin class (render/summary/modified/save gating).
 *
 * @package    assignfeedback_gradeconfidence
 * @covers     \assign_feedback_gradeconfidence
 * @copyright  2026 onwards
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class locallib_test extends \advanced_testcase {
    /** @var \assign */
    private $assign;
    /** @var \assign_feedback_gradeconfidence */
    private $plugin;
    /** @var \stdClass */
    private $grade;
    /** @var \stdClass */
    private $student;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $this->student = $gen->create_and_enrol($course, 'student');
        $instance = $gen->create_module('assign', [
            'course' => $course->id,
            'assignfeedback_gradeconfidence_enabled' => 1,
        ]);
        $cm = get_coursemodule_from_instance('assign', $instance->id);
        $context = \context_module::instance($cm->id);
        $this->setUser($teacher);
        $this->assign = new \assign($context, $cm, $course);
        $this->plugin = $this->assign->get_feedback_plugin_by_type('gradeconfidence');
        // A grade object keyed how our storage stores it (assignment + grade id).
        $this->grade = (object) ['id' => 4242, 'userid' => $this->student->id];
    }

    /**
     * Store a review against this test's assignment + grade.
     *
     * @param array $result
     */
    private function store(array $result): void {
        storage::save_review((int) $this->assign->get_context()->id, (int) $this->grade->id, (int) $this->grade->userid, $result);
    }

    public function test_flag_items_drops_unverified_quotes_as_a_backstop(): void {
        // Defence-in-depth (F3): even if an unverified quote reached the teacher panel — it should not,
        // run_store strips them at save — flag_items must not render it. Tested directly because a storage
        // round-trip never carries an unverified quote this far, so view() alone can't exercise this guard.
        $method = new \ReflectionMethod($this->plugin, 'flag_items');
        $method->setAccessible(true);
        $html = $method->invoke($this->plugin, [[
            'name' => 'Use of evidence', 'ailevel' => 1, 'ailabel' => 'Asserts', 'reasoning' => 'r',
            'evidence' => [
                ['text' => 'REAL VERIFIED QUOTE', 'verified' => true],
                ['text' => 'FABRICATED UNVERIFIED QUOTE', 'verified' => false],
            ],
        ]]);
        $this->assertStringContainsString('REAL VERIFIED QUOTE', $html);
        $this->assertStringNotContainsString('FABRICATED UNVERIFIED QUOTE', $html);
    }

    public function test_get_name_is_nonempty(): void {
        $this->assertNotEmpty($this->plugin->get_name());
    }

    public function test_is_feedback_modified_is_always_true(): void {
        // True so mod_assign calls save() on every grade save — our auto-review trigger.
        $this->assertTrue($this->plugin->is_feedback_modified($this->grade, new \stdClass()));
    }

    public function test_view_summary_without_review_is_empty_and_hides_link(): void {
        $showlink = true;
        $this->assertSame('', $this->plugin->view_summary($this->grade, $showlink));
        $this->assertFalse($showlink);
    }

    public function test_view_summary_consistent_shows_tick_no_link(): void {
        $this->store(['status' => 'ok', 'alert' => 'consistent', 'flags' => []]);
        $showlink = true;
        $summary = $this->plugin->view_summary($this->grade, $showlink);
        $this->assertStringContainsString('✓', $summary);
        $this->assertFalse($showlink);
    }

    public function test_view_summary_notify_shows_link(): void {
        $this->store(['status' => 'ok', 'alert' => 'notify', 'flags' => [
            ['name' => 'Evidence', 'ailevel' => 1, 'teacherlevel' => 3, 'gap' => -2],
        ]]);
        $showlink = false;
        $summary = $this->plugin->view_summary($this->grade, $showlink);
        $this->assertStringContainsString('⚠', $summary);
        $this->assertTrue($showlink);
    }

    public function test_view_renders_accessible_panel_and_drops_unverified_quotes(): void {
        $this->store(['status' => 'ok', 'alert' => 'notify', 'flags' => [[
            'name' => 'Use of evidence',
            'ailevel' => 1,
            'ailabel' => 'Asserts',
            'reasoning' => 'No sources cited.',
            'evidence' => [
                ['text' => 'Everyone knows this is true.', 'verified' => true],
                ['text' => 'FABRICATED QUOTE', 'verified' => false],
            ],
        ]]]);
        $html = $this->plugin->view($this->grade);
        // Accessible region with the criterion + verified quote; unverified quote dropped.
        $this->assertStringContainsString('role="region"', $html);
        $this->assertStringContainsString('Use of evidence', $html);
        $this->assertStringContainsString('Everyone knows this is true.', $html);
        $this->assertStringNotContainsString('FABRICATED QUOTE', $html);
    }

    public function test_view_consistent_is_a_status_region(): void {
        $this->store(['status' => 'ok', 'alert' => 'consistent', 'flags' => []]);
        $this->assertStringContainsString('role="status"', $this->plugin->view($this->grade));
    }

    public function test_save_off_mode_stores_nothing(): void {
        set_config('mode', 'off', 'aiplacement_gradeconfidence');
        $this->assertTrue($this->plugin->save($this->grade, new \stdClass()));
        $this->assertNull(storage::get_review((int) $this->grade->id));
    }

    public function test_save_auto_without_rubric_skips_gracefully(): void {
        // Default assign has no rubric → grading_source returns null → save stores nothing, never errors.
        set_config('mode', 'auto', 'aiplacement_gradeconfidence');
        $this->assertTrue($this->plugin->save($this->grade, new \stdClass()));
        $this->assertNull(storage::get_review((int) $this->grade->id));
    }

    /**
     * Store a notify review whose flag carries a distinctive criterion name + quote.
     */
    private function store_notify_with_secrets(): void {
        $this->store(['status' => 'ok', 'alert' => 'notify', 'flags' => [[
            'name' => 'SECRETCRITERION', 'ailevel' => 1, 'ailabel' => 'Asserts', 'teacherlevel' => 3,
            'gap' => -2, 'severity' => 'high', 'reasoning' => 'No sources cited.',
            'evidence' => [['text' => 'SECRETQUOTE', 'verified' => true]],
        ]]]);
    }

    #[\PHPUnit\Framework\Attributes\Group('security')]
    public function test_student_never_sees_the_internal_flags_or_quotes(): void {
        $this->store_notify_with_secrets();
        $this->setUser($this->student);

        $view = $this->plugin->view($this->grade);
        // The neutral assurance signal + explainer link only — no criterion, no quote, no "worth a look".
        $this->assertStringContainsString(
            get_string('studentreviewed', 'assignfeedback_gradeconfidence'),
            $view
        );
        $this->assertStringContainsString('explain.php', $view);
        $this->assertStringNotContainsString('SECRETCRITERION', $view);
        $this->assertStringNotContainsString('SECRETQUOTE', $view);

        $showlink = true;
        $summary = $this->plugin->view_summary($this->grade, $showlink);
        $this->assertStringNotContainsString('SECRETCRITERION', $summary);
        $this->assertStringNotContainsString('SECRETQUOTE', $summary);
    }

    public function test_teacher_still_sees_the_full_panel(): void {
        $this->store_notify_with_secrets();
        // We are still the teacher from setUp (has mod/assign:grade).
        $view = $this->plugin->view($this->grade);
        $this->assertStringContainsString('SECRETCRITERION', $view);
        $this->assertStringContainsString('SECRETQUOTE', $view);
    }

    #[\PHPUnit\Framework\Attributes\Group('security')]
    public function test_student_sees_nothing_when_review_did_not_complete(): void {
        // A failed/partial review must not even disclose to the student that a review was attempted.
        $this->store(['status' => 'error', 'alert' => 'partial', 'flags' => []]);
        $this->setUser($this->student);
        $this->assertSame('', $this->plugin->view($this->grade));
        $showlink = true;
        $this->assertSame('', $this->plugin->view_summary($this->grade, $showlink));
    }

    public function test_teacher_sees_advisory_detection_with_caveat(): void {
        $this->store([
            'status' => 'ok', 'alert' => 'consistent', 'flags' => [], 'detection' => 'high',
        ]);
        $view = $this->plugin->view($this->grade);
        $this->assertStringContainsString(
            get_string('aidetection_high', 'assignfeedback_gradeconfidence'),
            $view
        );
        $this->assertStringContainsString(
            get_string('aidetectioncaveat', 'assignfeedback_gradeconfidence'),
            $view
        );
    }

    #[\PHPUnit\Framework\Attributes\Group('security')]
    public function test_student_never_sees_detection(): void {
        $this->store([
            'status' => 'ok', 'alert' => 'consistent', 'flags' => [], 'detection' => 'high',
        ]);
        $this->setUser($this->student);
        $view = $this->plugin->view($this->grade);
        $this->assertStringNotContainsString(
            get_string('aidetection_high', 'assignfeedback_gradeconfidence'),
            $view
        );
    }

    public function test_teacher_sees_specific_toolong_message(): void {
        $this->store(['status' => 'toolong', 'alert' => 'partial', 'flags' => []]);
        $this->assertStringContainsString(
            get_string('reviewtoolong', 'assignfeedback_gradeconfidence'),
            $this->plugin->view($this->grade)
        );
    }

    public function test_student_sees_signal_for_a_consistent_review(): void {
        $this->store(['status' => 'ok', 'alert' => 'consistent', 'flags' => []]);
        $this->setUser($this->student);
        $this->assertStringContainsString(
            get_string('studentreviewed', 'assignfeedback_gradeconfidence'),
            $this->plugin->view($this->grade)
        );
    }

    public function test_grading_action_absent_in_auto_mode(): void {
        set_config('mode', 'auto', 'aiplacement_gradeconfidence');
        $this->assertSame([], $this->plugin->get_grading_actions());
    }

    public function test_grading_action_present_for_grader_in_manual_mode(): void {
        set_config('mode', 'manual', 'aiplacement_gradeconfidence');
        $this->assertArrayHasKey('review', $this->plugin->get_grading_actions());
    }

    #[\PHPUnit\Framework\Attributes\Group('security')]
    public function test_grading_action_hidden_from_students_in_manual_mode(): void {
        set_config('mode', 'manual', 'aiplacement_gradeconfidence');
        $this->setUser($this->student);
        $this->assertSame([], $this->plugin->get_grading_actions());
    }

    public function test_assessment_review_renders_neutrally_not_as_flags(): void {
        // A library-rubric review has no teacher level on any criterion → present as an AI assessment.
        $this->store(['status' => 'ok', 'alert' => 'notify', 'flags' => [[
            'name' => 'Idea', 'ailevel' => 1, 'ailabel' => 'OK', 'teacherlevel' => null,
            'gap' => 1, 'severity' => 'low', 'reasoning' => 'r', 'evidence' => [],
        ]]]);
        $view = $this->plugin->view($this->grade);
        $this->assertStringContainsString(
            get_string('assessmentheading', 'assignfeedback_gradeconfidence'),
            $view
        );
        $this->assertStringNotContainsString(
            get_string('reviewflags', 'assignfeedback_gradeconfidence', 1),
            $view
        );
    }

    public function test_save_does_not_auto_review_in_manual_mode(): void {
        set_config('mode', 'manual', 'aiplacement_gradeconfidence');
        $this->assertTrue($this->plugin->save($this->grade, new \stdClass()));
        $this->assertNull(storage::get_review((int) $this->grade->id));
    }
}
