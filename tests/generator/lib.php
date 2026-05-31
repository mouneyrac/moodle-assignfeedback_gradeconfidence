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

use aiplacement_gradeconfidence\local\credit_store;

/**
 * Test data generator for assignfeedback_gradeconfidence.
 *
 * The on-demand check button only renders for a graded-but-unreviewed student, so Behat needs a way to
 * seed a bare assign grade (no clean core step does this) and a per-teacher credit row at any state — so
 * the button, the "checks left" count, and the exhausted note can be exercised without a live AI provider.
 *
 * @package    assignfeedback_gradeconfidence
 * @category   test
 * @copyright  2026 onwards
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assignfeedback_gradeconfidence_generator extends component_generator_base {
    /**
     * Reset the generator (state lives in the DB, which the harness rolls back).
     */
    public function reset(): void {
    }

    /**
     * Create a bare assign grade row for a user — the precondition for the on-demand check button.
     *
     * @param array $record Keys: cmid (resolved from "assign"), userid (from "user"), optional grade, grader.
     * @return \stdClass The created assign_grades record.
     */
    public function create_grade(array $record): \stdClass {
        global $DB;
        $data = (object) $record;
        if (empty($data->cmid)) {
            throw new \coding_exception('create_grade requires an "assign" (activity name).');
        }
        if (empty($data->userid)) {
            throw new \coding_exception('create_grade requires a "user".');
        }
        $cm = get_coursemodule_from_id('assign', (int) $data->cmid, 0, false, MUST_EXIST);
        $now = time();
        $grade = (object) [
            'assignment' => (int) $cm->instance,
            'userid' => (int) $data->userid,
            'timecreated' => $now,
            'timemodified' => $now,
            'grader' => isset($data->grader) ? (int) $data->grader : get_admin()->id,
            'grade' => isset($data->grade) ? (float) $data->grade : 50.0,
            'attemptnumber' => 0,
        ];
        $grade->id = $DB->insert_record('assign_grades', $grade);
        return $grade;
    }

    /**
     * Seed a per-teacher credit allowance row at an exact (allowance, used) state.
     *
     * @param array $record Keys: courseid (from "course"), userid (from "user"), optional allowance, used.
     * @return void
     */
    public function create_credit(array $record): void {
        $data = (object) $record;
        if (empty($data->courseid)) {
            throw new \coding_exception('create_credit requires a "course".');
        }
        if (empty($data->userid)) {
            throw new \coding_exception('create_credit requires a "user".');
        }
        $contextid = (int) \context_course::instance((int) $data->courseid)->id;
        $allowance = isset($data->allowance) ? (int) $data->allowance : 5;
        $used = isset($data->used) ? (int) $data->used : 0;
        credit_store::ensure($contextid, (int) $data->userid, $allowance);
        for ($i = 0; $i < $used; $i++) {
            credit_store::consume($contextid, (int) $data->userid);
        }
    }
}
