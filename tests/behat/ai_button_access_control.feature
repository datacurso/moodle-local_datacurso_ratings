@local @local_datacurso_ratings
Feature: AI analysis button visibility based on capabilities
  As a teacher or manager
  I should only see the AI analysis buttons if I have the corresponding capability
  So that AI features can be controlled per role

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "activities" exist:
      | activity | course | name       | idnumber |
      | quiz     | C1     | Test Quiz  | quiz1    |
    And the following "users" exist:
      | username  | firstname | lastname | email                 |
      | teacher1  | Teacher   | One      | teacher1@example.com  |
      | teacher2  | Teacher   | Two      | teacher2@example.com  |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | teacher2 | C1     | editingteacher |
    And the following config values are set as admin:
      | enabled | 1 | local_datacurso_ratings |

  @javascript @MDL-INT-017
  Scenario: Teacher with AI capability sees the course AI analysis button
    Given the following "permission overrides" exist:
      | capability                                          | permission | role           | contextlevel | reference |
      | local/datacurso_ratings:viewcoursereport             | Allow      | editingteacher | Course       | C1        |
      | local/datacurso_ratings:generateanalysiscourse       | Allow      | editingteacher | Course       | C1        |
    When I am on the "Course 1" course page logged in as "teacher1"
    And I navigate to "Reports > Activity/Resource Ratings Report" in current page administration
    Then ".btn-generate-ai-course" "css_element" should exist

  @javascript @MDL-INT-017
  Scenario: Teacher without AI capability does not see the course AI analysis button
    Given the following "permission overrides" exist:
      | capability                                          | permission | role           | contextlevel | reference |
      | local/datacurso_ratings:viewcoursereport             | Allow      | editingteacher | Course       | C1        |
      | local/datacurso_ratings:generateanalysiscourse       | Prohibit   | editingteacher | Course       | C1        |
    When I am on the "Course 1" course page logged in as "teacher2"
    And I navigate to "Reports > Activity/Resource Ratings Report" in current page administration
    Then ".btn-generate-ai-course" "css_element" should not exist
