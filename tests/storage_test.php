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

/**
 * Tests for the adapter storage seam (delegates to the engine run_store, keyed by 'assign' + grade id).
 *
 * @package    assignfeedback_gradeconfidence
 * @copyright  2026 onwards
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\assignfeedback_gradeconfidence\storage::class)]
final class storage_test extends \advanced_testcase {
    public function test_save_then_get_roundtrips(): void {
        $this->resetAfterTest();
        $flags = [[
            'name' => 'Use of evidence', 'ailevel' => 1, 'ailabel' => 'Asserts', 'teacherlevel' => 3,
            'gap' => -2, 'severity' => 'high', 'reasoning' => 'No sources cited.',
            'evidence' => [['text' => 'Everyone knows this.', 'verified' => true]],
        ]];
        storage::save_review(123, 50, 500, ['status' => 'ok', 'alert' => 'notify', 'flags' => $flags]);

        $review = storage::get_review(50);
        $this->assertSame('ok', $review['status']);
        $this->assertSame('notify', $review['alert']);
        $this->assertCount(1, $review['flags']);
        $this->assertSame('Use of evidence', $review['flags'][0]['name']);
        $this->assertSame('Everyone knows this.', $review['flags'][0]['evidence'][0]['text']);
    }

    public function test_get_review_returns_null_when_absent(): void {
        $this->resetAfterTest();
        $this->assertNull(storage::get_review(999));
    }

    public function test_delete_review_removes_it(): void {
        $this->resetAfterTest();
        storage::save_review(123, 70, 700, ['status' => 'ok', 'alert' => 'consistent', 'flags' => []]);
        storage::delete_review(70);
        $this->assertNull(storage::get_review(70));
    }
}
