@gradereport @gradereport_markingguide
Feature: Selecting an assignment option generates a markingguide report
  In order to generate a markingguide report
  As an teacher
  I need to check that the markingguide report is correctly displayed

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student10@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activity" exists:
      | activity                              | assign                           |
      | course                                | C1                               |
      | idnumber                              | assign1                          |
      | name                                  | Test assignment 1 name           |
      | intro                                 | Test assignment description      |
      | section                               | 1                                |
      | assignsubmission_file_enabled         | 1                                |
      | assignsubmission_onlinetext_enabled   | 1                                |
      | assignsubmission_file_maxfiles        | 1                                |
      | assignsubmission_file_maxsizebytes    | 1000                             |
      | assignfeedback_comments_enabled       | 1                                |
      | assignfeedback_file_enabled           | 1                                |
      | assignfeedback_comments_commentinline | 1                                |
    And I am on the "Test assignment 1 name" "assign activity editing" page logged in as teacher1
    And I set the following fields to these values:
      | Grading method  | Marking guide               |
    And I press "Save and return to course"
  # Defining a marking guide
    And I go to "Test assignment 1 name" advanced grading definition page
    And I set the following fields to these values:
      | Name        | Assignment 1 marking guide     |
      | Description | Marking guide test description |
    And I define the following marking guide:
      | Criterion name    | Description for students         | Description for markers         | Maximum score |
      | Guide criterion A | Guide A description for students | Guide A description for markers | 30            |
      | Guide criterion B | Guide B description for students | Guide B description for markers | 30            |
      | Guide criterion C | Guide C description for students | Guide C description for markers | 40            |
    And I press "Save marking guide and make it ready"
    And I wait "2" seconds
    And I go to "Student 1" "Test assignment 1 name" activity advanced grading page
    And I wait "2" seconds
    And I grade by filling the marking guide with:
      | Guide criterion A | 25 | Very good  |
      | Guide criterion B | 20 |            |
      | Guide criterion C | 35 | Nice!      |
    And I press "Save changes"

  @javascript
  Scenario: A teacher views a markingguide report.
    Given I am logged in as "teacher1"
    And I am on "Course 1" course homepage
    When I navigate to "View > Marking Guide report" in the course gradebook
    And I set the field "Select assignment" to "Test assignment 1 name"
    And I press "Submit"
    Then "Student 1" row "Grade" column of "generaltable" table should contain "80"
