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
 * Privacy provider for assignfeedback_gradeconfidence.
 *
 * @package    assignfeedback_gradeconfidence
 * @copyright  2026 onwards
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignfeedback_gradeconfidence\privacy;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/assign/locallib.php');

use aiplacement_gradeconfidence\local\run_store;
use assignfeedback_gradeconfidence\storage;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use mod_assign\privacy\assign_plugin_request_data;
use mod_assign\privacy\useridlist;

/**
 * Privacy provider for the assign adapter. The reviewed submission text is sent to core_ai (declared
 * here); the stored review rows are owned and exported by the engine's privacy provider. This provider
 * only cascades mod_assign-driven deletion (assignment / grade) into the engine store.
 *
 * @package    assignfeedback_gradeconfidence
 * @copyright  2026 onwards
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \mod_assign\privacy\assignfeedback_provider,
    \mod_assign\privacy\assignfeedback_user_provider {
    #[\Override]
    public static function get_metadata(collection $collection): collection {
        // Submission text leaves the site for review; the stored outcome is described by the engine.
        $collection->link_subsystem('core_ai', 'privacy:metadata:coreai');
        return $collection;
    }

    /**
     * Context discovery is handled by the engine provider (runs are keyed by context).
     *
     * @param int $userid
     * @param contextlist $contextlist
     */
    public static function get_context_for_userid_within_feedback(int $userid, contextlist $contextlist) {
        // No-op: the engine provider discovers contexts from {aiplacement_gc_run}.
    }

    /**
     * User-id discovery is handled by the engine provider.
     *
     * @param useridlist $useridlist
     */
    public static function get_student_user_ids(useridlist $useridlist) {
        // No-op: the engine provider discovers users from {aiplacement_gc_run}.
    }

    /**
     * User-in-context discovery is handled by the engine provider.
     *
     * @param userlist $userlist
     */
    public static function get_userids_from_context(userlist $userlist) {
        // No-op: the engine provider discovers users from {aiplacement_gc_run}.
    }

    /**
     * Export is handled by the engine provider (it owns the stored rows).
     *
     * @param assign_plugin_request_data $exportdata
     */
    public static function export_feedback_user_data(assign_plugin_request_data $exportdata) {
        // No-op: the engine provider exports {aiplacement_gc_run}/{aiplacement_gc_result} by context.
    }

    #[\Override]
    public static function delete_feedback_for_context(assign_plugin_request_data $requestdata) {
        run_store::delete_for_context($requestdata->get_context()->id);
    }

    #[\Override]
    public static function delete_feedback_for_grade(assign_plugin_request_data $requestdata) {
        storage::delete_review((int) $requestdata->get_pluginobject()->id);
    }

    /**
     * Delete review rows for the given grade ids.
     *
     * @param assign_plugin_request_data $deletedata
     */
    public static function delete_feedback_for_grades(assign_plugin_request_data $deletedata) {
        storage::delete_reviews($deletedata->get_gradeids());
    }
}
