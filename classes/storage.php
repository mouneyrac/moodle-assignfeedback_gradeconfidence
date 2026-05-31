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

use aiplacement_gradeconfidence\local\run_store;

/**
 * Thin assign-flavoured seam over the engine's canonical store ({aiplacement_gc_run} +
 * {aiplacement_gc_result}). Reviews are keyed in the engine by sourcetype 'assign' + the assign_grades
 * id, so the assign adapter stays free of storage detail and the store can be reused by other surfaces.
 *
 * @package    assignfeedback_gradeconfidence
 * @copyright  2026 onwards
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class storage {
    /** @var string The surface type recorded in the engine store. */
    public const SOURCETYPE = 'assign';

    /**
     * Upsert the review outcome for one grade.
     *
     * @param int $contextid The assignment (module) context id.
     * @param int $gradeid assign_grades.id.
     * @param int $userid Graded student.
     * @param array $result grader::review() output (status, alert, flags).
     * @return void
     */
    public static function save_review(int $contextid, int $gradeid, int $userid, array $result): void {
        run_store::save($contextid, self::SOURCETYPE, $gradeid, $userid, $result);
    }

    /**
     * Fetch the stored review for a grade.
     *
     * @param int $gradeid assign_grades.id.
     * @return array{alert: string, status: string, flags: array}|null
     */
    public static function get_review(int $gradeid): ?array {
        return run_store::get_by_source(self::SOURCETYPE, $gradeid);
    }

    /**
     * Delete the stored review for a grade (deletion cascade from mod_assign).
     *
     * @param int $gradeid assign_grades.id.
     * @return void
     */
    public static function delete_review(int $gradeid): void {
        run_store::delete_for_source(self::SOURCETYPE, $gradeid);
    }

    /**
     * Delete stored reviews for several grades.
     *
     * @param array $gradeids assign_grades ids.
     * @return void
     */
    public static function delete_reviews(array $gradeids): void {
        run_store::delete_for_sources(self::SOURCETYPE, $gradeids);
    }
}
