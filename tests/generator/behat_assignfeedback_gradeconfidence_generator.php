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
 * Behat data generator for assignfeedback_gradeconfidence.
 *
 * Exposes:
 *   the following "assignfeedback_gradeconfidence > grades" exist   — a bare assign grade for a user.
 *   the following "assignfeedback_gradeconfidence > credits" exist  — a per-teacher allowance row.
 *
 * @package    assignfeedback_gradeconfidence
 * @category   test
 * @copyright  2026 onwards
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_assignfeedback_gradeconfidence_generator extends behat_generator_base {
    /**
     * Entities this generator can create.
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'grades' => [
                'singular' => 'grade',
                'datagenerator' => 'grade',
                'required' => ['assign', 'user'],
                'switchids' => ['assign' => 'cmid', 'user' => 'userid'],
            ],
            'credits' => [
                'singular' => 'credit',
                'datagenerator' => 'credit',
                'required' => ['course', 'user'],
                'switchids' => ['course' => 'courseid', 'user' => 'userid'],
            ],
        ];
    }

    /**
     * Resolve an assignment activity name (or idnumber) to its course module id.
     *
     * @param string $identifier Activity name or idnumber.
     * @return int The cmid.
     */
    protected function get_assign_id(string $identifier): int {
        return $this->get_cm_by_activity_name('assign', $identifier)->id;
    }
}
