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
 * Sends the exception-based notification (UX §7.2): only on a material discrepancy (alert = notify).
 * Uses the engine's message provider (aiplacement_gradeconfidence/gradeflag) so notification preferences live
 * in one place.
 *
 * @package    assignfeedback_gradeconfidence
 * @copyright  2026 onwards
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notifier {
    /**
     * Notify the grading teacher that a saved grade may need a second look.
     *
     * @param int $touserid The teacher to notify.
     * @param string $studentname Display name of the graded student.
     * @param string $assignmentname The assignment name.
     * @param \moodle_url $url Link back to the grading screen.
     * @param array $flags The flag list (to summarise the material criterion).
     * @return void
     */
    public static function flag(int $touserid, string $studentname, string $assignmentname, \moodle_url $url, array $flags): void {
        $material = null;
        foreach ($flags as $f) {
            if (abs((int) ($f['gap'] ?? 0)) >= 2) {
                $material = $f;
                break;
            }
        }
        $criterion = $material['name'] ?? '';

        $message = new \core\message\message();
        $message->component = 'aiplacement_gradeconfidence';
        $message->name = 'gradeflag';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $touserid;
        $message->subject = get_string('messageprovider:gradeflag', 'aiplacement_gradeconfidence');
        $message->fullmessage = "A grade you just saved for {$studentname} on \"{$assignmentname}\" may need a "
            . "second look: {$criterion}.";
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = '<p>A grade you just saved for <strong>' . s($studentname) . '</strong> on "'
            . s($assignmentname) . '" may need a second look: <strong>' . s($criterion) . '</strong>.</p>';
        $message->smallmessage = "Grade for {$studentname} may need a second look: {$criterion}";
        $message->notification = 1;
        $message->contexturl = $url->out(false);
        $message->contexturlname = 'Review grading';

        message_send($message);
    }
}
