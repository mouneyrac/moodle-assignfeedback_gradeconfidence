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
 * Upgrade steps for assignfeedback_gradeconfidence.
 *
 * @package    assignfeedback_gradeconfidence
 * @copyright  2026 onwards
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the assign adapter.
 *
 * @param int $oldversion The currently-installed version.
 * @return bool
 */
function xmldb_assignfeedback_gradeconfidence_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026053000) {
        // The canonical store moved to the engine ({aiplacement_gc_run}/{aiplacement_gc_result}, created
        // by the engine upgrade which runs first as a dependency). Migrate any existing rows, then drop
        // the adapter-owned table.
        $old = new xmldb_table('assignfeedback_gc_review');
        if ($dbman->table_exists($old)) {
            $rs = $DB->get_recordset('assignfeedback_gc_review');
            foreach ($rs as $row) {
                $cm = get_coursemodule_from_instance('assign', (int) $row->assignment, 0, false, IGNORE_MISSING);
                if (!$cm) {
                    continue;
                }
                $context = context_module::instance($cm->id);
                $result = [
                    'status' => $row->status,
                    'alert' => $row->alert,
                    'flags' => json_decode((string) $row->flags, true) ?: [],
                ];
                \aiplacement_gradeconfidence\local\run_store::save(
                    (int) $context->id,
                    'assign',
                    (int) $row->grade,
                    (int) $row->userid,
                    $result
                );
            }
            $rs->close();
            $dbman->drop_table($old);
        }

        upgrade_plugin_savepoint(true, 2026053000, 'assignfeedback', 'gradeconfidence');
    }

    return true;
}
