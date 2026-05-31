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
 * Tests for the exception-based notifier.
 *
 * @package    assignfeedback_gradeconfidence
 * @covers     \assignfeedback_gradeconfidence\notifier
 * @copyright  2026 onwards
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class notifier_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        // The message provider lives in the engine placement; notifications only flow when the placement
        // is enabled (get_message_providers() filters out disabled plugins).
        \core\plugininfo\aiplacement::enable_plugin('gradeconfidence', 1);
    }

    public function test_flag_sends_one_notification_naming_the_material_criterion(): void {
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $sink = $this->redirectMessages();

        $teacher = $this->getDataGenerator()->create_user();
        $url = new \moodle_url('/mod/assign/view.php', ['id' => 1]);
        // Two flags; only the gap>=2 one is "material" and should be named.
        $flags = [
            ['name' => 'Language', 'gap' => 1],
            ['name' => 'Use of evidence', 'gap' => 2],
        ];

        notifier::flag($teacher->id, 'Priya Student', 'Essay 1', $url, $flags);

        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $message = reset($messages);
        $this->assertEquals($teacher->id, $message->useridto);
        $this->assertSame('aiplacement_gradeconfidence', $message->component);
        $this->assertSame('gradeflag', $message->eventtype);
        $this->assertSame(1, (int) $message->notification);
        $this->assertStringContainsString('Use of evidence', $message->fullmessage);
        $this->assertStringContainsString('Priya Student', $message->fullmessage);
    }

    public function test_flag_html_is_escaped(): void {
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $sink = $this->redirectMessages();

        $teacher = $this->getDataGenerator()->create_user();
        $url = new \moodle_url('/mod/assign/view.php', ['id' => 1]);
        notifier::flag($teacher->id, '<b>Hacker</b>', 'Essay', $url, [['name' => 'Crit', 'gap' => 2]]);

        $messages = $sink->get_messages();
        $message = reset($messages);
        $this->assertStringNotContainsString('<b>Hacker</b>', $message->fullmessagehtml);
        $this->assertStringContainsString('&lt;b&gt;Hacker', $message->fullmessagehtml);
    }
}
