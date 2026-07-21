@local @local_datacurso_ratings
Feature: Rating widget visibility on supported activity types
  In order to collect ratings on course activities
  As a student
  I need to see the rating widget on supported module pages

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | One      | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the following config values are set as admin:
      | enabled | 1 | local_datacurso_ratings |

  @javascript @MDL-INT-019
  Scenario Outline: Widget appears on <modtype> activity page
    Given the following "activity" exists:
      | activity | <modtype>        |
      | course   | C1               |
      | name     | Test <modtype>   |
      | idnumber | <modtype>1       |
    When I am on the "Test <modtype>" "<modtype> activity" page logged in as "student1"
    Then "div.local-dcr-rate" "css_element" should exist

    Examples:
      | modtype     |
      | assign      |
      | book        |
      | chat        |
      | choice      |
      | data        |
      | feedback    |
      | forum       |
      | glossary    |
      | lesson      |
      | lti         |
      | page        |
      | quiz        |
      | survey      |
      | wiki        |
      | workshop    |

  @javascript @MDL-INT-019
  Scenario: Widget appears on resource activity page
    Given the following "activity" exists:
      | activity        | resource                                   |
      | course          | C1                                         |
      | name            | Test resource                              |
      | idnumber        | resource1                                  |
      | defaultfilename | mod/resource/tests/fixtures/samplefile.txt |
      | uploaded        | 1                                          |
    When I am on the "Test resource" "resource activity" page logged in as "student1"
    Then "div.local-dcr-rate" "css_element" should exist

  @javascript @MDL-INT-019
  Scenario: Widget appears on folder activity page
    Given the following "activity" exists:
      | activity | folder      |
      | course   | C1          |
      | name     | Test folder |
      | idnumber | folder1     |
    When I am on the "Test folder" "folder activity" page logged in as "student1"
    Then "div.local-dcr-rate" "css_element" should exist

  @javascript @MDL-INT-019
  Scenario: Widget appears on url activity page
    Given the following "activity" exists:
      | activity    | url                 |
      | course      | C1                  |
      | name        | Test url            |
      | idnumber    | url1                |
      | externalurl | https://moodle.org/ |
    When I am on the "Test url" "url activity" page logged in as "student1"
    Then "div.local-dcr-rate" "css_element" should exist

  @javascript @MDL-INT-019
  Scenario: Widget appears on imscp activity page
    Given the following "activity" exists:
      | activity        | imscp                                       |
      | course          | C1                                          |
      | name            | Test imscp                                  |
      | idnumber        | imscp1                                      |
      | packagefilepath | mod/imscp/tests/packages/singlescobasic.zip |
    When I am on the "Test imscp" "imscp activity" page logged in as "student1"
    Then "div.local-dcr-rate" "css_element" should exist

  @javascript @MDL-INT-019
  Scenario: Widget appears on scorm activity page
    Given the following "activity" exists:
      | activity        | scorm                                          |
      | course          | C1                                             |
      | name            | Test scorm                                     |
      | idnumber        | scorm1                                         |
      | packagefilepath | mod/scorm/tests/packages/singlesco_scorm12.zip |
    When I am on the "Test scorm" "scorm activity" page logged in as "student1"
    Then "div.local-dcr-rate" "css_element" should exist

  @javascript @MDL-INT-019
  Scenario: Widget appears on h5pactivity activity page
    Given the following "activity" exists:
      | activity        | h5pactivity                          |
      | course          | C1                                   |
      | name            | Test h5pactivity                     |
      | idnumber        | h5pactivity1                         |
      | packagefilepath | h5p/tests/fixtures/filltheblanks.h5p |
    When I am on the "Test h5pactivity" "h5pactivity activity" page logged in as "student1"
    Then "div.local-dcr-rate" "css_element" should exist

  # hvp is a third-party plugin (not part of Moodle core) — not tested here.
