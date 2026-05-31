@assignfeedback @assignfeedback_gradeconfidence @aiplacement_gradeconfidence @javascript
Feature: On-demand Grade Confidence checks and per-teacher credits on the grading screen
  In order to keep AI spend predictable while grading
  As a teacher
  When Grade Confidence is in on-demand mode I can check a grade when I choose, within an allowance.

  # Scope note: a live AI provider cannot run in Behat, so these scenarios do NOT drive an actual review
  # or its consumption — that is covered deterministically by the PHPUnit suites (credit_guard_test,
  # locallib_test with a fake text_client). These assert only the on-demand UI integration: the per-student
  # check button appears for a graded-unreviewed student, the remaining-credit count renders, and an
  # exhausted allowance replaces the button with the "request more" note.

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
    And the following "assignfeedback_gradeconfidence > grades" exist:
      | assign  | user     | grade |
      | Essay 1 | student1 | 60    |

  Scenario: On-demand mode offers a per-student check button on the grading list
    Given the following config values are set as admin:
      | mode | manual | aiplacement_gradeconfidence |
    And I am on the "Essay 1" "assign activity" page logged in as teacher1
    When I navigate to "Submissions" in current page administration
    Then I should see "Ask Grade Confidence to check this grade"

  Scenario: The remaining credit count shows next to the check button
    Given the following config values are set as admin:
      | mode           | manual | aiplacement_gradeconfidence |
      | defaultcredits | 5      | aiplacement_gradeconfidence |
    And I am on the "Essay 1" "assign activity" page logged in as teacher1
    When I navigate to "Submissions" in current page administration
    Then I should see "Ask Grade Confidence to check this grade"
    And I should see "5 checks left"

  Scenario: An exhausted allowance replaces the button with a request-more note
    Given the following config values are set as admin:
      | mode           | manual | aiplacement_gradeconfidence |
      | defaultcredits | 2      | aiplacement_gradeconfidence |
    And the following "assignfeedback_gradeconfidence > credits" exist:
      | course | user     | allowance | used |
      | C1     | teacher1 | 2         | 2    |
    And I am on the "Essay 1" "assign activity" page logged in as teacher1
    When I navigate to "Submissions" in current page administration
    Then I should see "AI check allowance used up for this course"
    And I should see "Request more checks"
    And I should not see "Ask Grade Confidence to check this grade"
