@assignfeedback @assignfeedback_gradeconfidence @javascript
Feature: Grade Confidence integrates with assignment grading without breaking it
  In order to grade fairly and consistently
  As a teacher
  When Grade Confidence is enabled on an assignment the grading screens keep working.

  # Scope note: the review BEHAVIOUR (diff, alert tiers, quote verification, the teacher panel, the
  # student-only neutral signal, accessible markup, and the save -> review path) is covered
  # deterministically by the assignfeedback_gradeconfidence PHPUnit suite with a fake text_client, and
  # was confirmed live end-to-end against OpenAI. These Behat scenarios only assert the UI integration:
  # that enabling the plugin (the common no-provider CI case) never breaks the teacher's submissions
  # screen or the student's own submission page. They deliberately do NOT drive the assign grade form.

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Teacher   | One      |
      | student1 | Priya     | Student  |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | course | name    | assignsubmission_onlinetext_enabled | assignfeedback_gradeconfidence_enabled |
      | assign   | C1     | Essay 1 | 1                                   | 1                                      |
    And the following "mod_assign > submissions" exist:
      | assign  | user     | onlinetext                                      |
      | Essay 1 | student1 | Social media should be regulated more strictly. |

  Scenario: The teacher submissions screen works with Grade Confidence enabled and no provider
    Given I am on the "Essay 1" "assign activity" page logged in as teacher1
    When I navigate to "Submissions" in current page administration
    Then I should see "Priya Student"
    And I should not see "Exception"
    And I should not see "Coding error detected"

  Scenario: The student's own submission page works with Grade Confidence enabled
    Given I am on the "Essay 1" "assign activity" page logged in as student1
    Then I should see "Online text"
    And I should not see "Exception"
    And I should not see "Coding error detected"
