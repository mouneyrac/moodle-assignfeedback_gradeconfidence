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

namespace assignfeedback_gradeconfidence\privacy;

use assignfeedback_gradeconfidence\storage;
use core_privacy\local\metadata\collection;
use mod_assign\privacy\assign_plugin_request_data;

/**
 * Tests for the assign adapter privacy provider (declares the core_ai flow; delegates deletion).
 *
 * @package    assignfeedback_gradeconfidence
 * @covers     \assignfeedback_gradeconfidence\privacy\provider
 * @copyright  2026 onwards
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider_test extends \advanced_testcase {
    /**
     * Build a real assign + module context.
     *
     * @return array{0: \assign, 1: \context_module}
     */
    private function make_assign(): array {
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $instance->id);
        $context = \context_module::instance($cm->id);
        return [new \assign($context, $cm, $course), $context];
    }

    public function test_get_metadata_declares_core_ai_flow(): void {
        $items = provider::get_metadata(new collection('assignfeedback_gradeconfidence'))->get_collection();
        $names = array_map(fn($i) => $i->get_name(), $items);
        $this->assertContains('core_ai', $names);
        foreach ($items as $item) {
            $this->assertTrue(get_string_manager()->string_exists(
                $item->get_summary(),
                'assignfeedback_gradeconfidence'
            ));
        }
    }

    public function test_delete_feedback_for_context_delegates_to_engine_store(): void {
        $this->resetAfterTest();
        [$assign, $context] = $this->make_assign();
        storage::save_review((int) $context->id, 60, 600, ['status' => 'ok', 'alert' => 'notify', 'flags' => []]);
        // A review in another context must survive.
        storage::save_review($context->id + 999, 61, 601, ['status' => 'ok', 'alert' => 'consistent', 'flags' => []]);

        provider::delete_feedback_for_context(new assign_plugin_request_data($context, $assign));

        $this->assertNull(storage::get_review(60));
        $this->assertNotNull(storage::get_review(61));
    }

    public function test_delete_feedback_for_grade_delegates(): void {
        $this->resetAfterTest();
        [$assign, $context] = $this->make_assign();
        storage::save_review((int) $context->id, 70, 700, ['status' => 'ok', 'alert' => 'notify', 'flags' => []]);
        storage::save_review((int) $context->id, 71, 701, ['status' => 'ok', 'alert' => 'consistent', 'flags' => []]);

        provider::delete_feedback_for_grade(new assign_plugin_request_data($context, $assign, (object) ['id' => 70]));

        $this->assertNull(storage::get_review(70));
        $this->assertNotNull(storage::get_review(71));
    }
}
