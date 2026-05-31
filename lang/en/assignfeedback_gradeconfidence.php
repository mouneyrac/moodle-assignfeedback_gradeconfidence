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
 * Strings for assignfeedback_gradeconfidence.
 *
 * @package    assignfeedback_gradeconfidence
 * @copyright  2026 onwards
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$string['aidetection_high'] = 'AI-likelihood: high.';
$string['aidetection_low'] = 'AI-likelihood: low.';
$string['aidetection_medium'] = 'AI-likelihood: medium.';
$string['aidetectioncaveat'] = 'Advisory only — AI detectors are unreliable (high false-positive rates, especially for non-native writers). Not proof; never act on it alone.';
$string['aisuggests'] = 'Suggested Level {$a->ai}: {$a->ailabel}';
$string['assessmentheading'] = 'AI assessment — review it, then decide the grade yourself.';
$string['assessmentsummary'] = 'AI assessment of {$a} criterion/criteria';
$string['default'] = 'Enabled by default';
$string['default_help'] = 'If set, this feedback method will be enabled by default for all new assignments.';
$string['enabled'] = 'Grade Confidence';
$string['enabled_help'] = 'If enabled, when you save a grade Grade Confidence reviews your rubric selections for consistency and flags anything that looks off. Your grade is never changed automatically.';
$string['evidence'] = 'Evidence';
$string['exemplar'] = 'Model answer (optional)';
$string['libraryrubric'] = 'Rubric for AI review when there is no native rubric';
$string['creditsleft'] = '({$a} checks left)';
$string['creditsout'] = 'You have used your AI check allowance for this course. Ask an administrator to add more.';
$string['creditsoutshort'] = 'AI check allowance used up — ask an administrator for more.';
$string['libraryrubricnone'] = 'None';
$string['manualconfirm'] = '{$a} graded submission(s) will be reviewed against the rubric. Each is sent to the configured AI provider. Continue?';
$string['manualdone'] = 'Reviewed {$a->reviewed} submission(s): {$a->flagged} flagged for a second look, {$a->skipped} skipped (no rubric or submission).';
$string['manualreview'] = 'Check grading with Grade Confidence';
$string['pluginname'] = 'Grade Confidence';
$string['privacy:metadata:coreai'] = 'Submission text is sent to the site\'s AI subsystem (core_ai) to be reviewed by the configured AI provider.';
$string['reviewconsistent'] = 'Looks consistent — your rubric selections match the evidence.';
$string['reviewbudget'] = 'This course has reached its configured AI review budget, so no new review was run. Ask an administrator to raise the per-course budget.';
$string['reviewdone'] = 'Grade Confidence checked this grade.';
$string['reviewfailed'] = 'The check could not be completed (no AI provider configured, or the submission could not be read).';
$string['reviewflags'] = '{$a} point(s) worth a look';
$string['reviewnograde'] = 'Grade this submission first, then ask Grade Confidence to check it.';
$string['reviewnone'] = 'No AI review yet — save the grade to run one.';
$string['requestcheck'] = 'Ask Grade Confidence to check this grade';
$string['reviewpartial'] = 'The assistant could not fully review this submission (it may be too long, or no provider is configured).';
$string['reviewtoolong'] = 'This submission is too long for the AI to review. Shorten it, or raise the provider\'s maximum-tokens setting.';
$string['studentexplain'] = 'What does this mean?';
$string['studentreviewed'] = 'This grade was reviewed by AI for consistency with the rubric. Your teacher\'s grade is the one that counts.';
$string['viewtrace'] = 'View full review trace';
$string['youchose'] = 'You chose Level {$a->teacher}: {$a->teacherlabel}';
