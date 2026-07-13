# Marking Guide Report
Copyright (C) 2026 [Brickfield Education Labs](https://www.brickfield.ie/)

## What is the Marking Guide Report?
The Marking Guide report is a grade report plugin for Moodle that gives teachers a complete view of marking guide grading results for all students in a course, in a single table.​

For each marking guide-graded activity, the report shows every marking guide criterion as a column, with each student's score, level description, and remark per criterion, plus their overall grade. The table is downloadable as CSV or Excel.

This supports standardised grading by providing this overview of all grades and students in one report.

## License
2026 Onward [Brickfield Education Labs](https://www.brickfield.ie)

## Version support
This plugin has been developed to work on Moodle releases 4.5, 5.0, 5.1, and 5.2.

## Funding credits
Initial funding for this plugin was provided by the National Institute for Digital Learning at Dublin City University.

## Development
This plugin has been developed and is maintained by Brickfield Education Labs.

If you wish to contribute funding to the ongoing development of features and / or maintenance of the plugin - please contact [support@brickfield.ie](mailto:support@brickfield.ie).

## Important Links
* [Code repository](https://github.com/brickfield/moodle-gradereport_markingguide)
* [Plugin directory](https://moodle.org/plugins/gradereport_markingguide)
* [Marking Guide user guide](https://docs.brickfield.ie/gradereport-markingguide/)

## Installation
1. Unzip and copy the "markingguide" folder into your Moodle's "grade/report/" folder
2. Log in as a site administrator and visit **Site administration > Notifications** to complete the installation.

Further installation instructions can be found on the
"[Installing plugins](http://docs.moodle.org/en/Installing_contributed_modules_or_plugins)" Moodle documentation page.

## Configuration

After installation, one global setting is available under **Site administration > Grades > Report settings > Marking Guide report**:
* **Display URL params** — when enabled, submitting the activity-selection form redirects to a URL that includes all report options as query parameters. This makes report links bookmarkable and shareable. Disabled by default.

## Usage

* In a course, go to **Grades**.
* Select the **Marking Guide report** option from the grade report navigation.
* Use the **Select activity** dropdown to choose a marking guide-graded activity.
* Optionally expand **Data to include** to toggle the following columns:
  * Display level - shows the marking guide level description alongside the score (on by default).
  * Display remarks - shows the grader's per-criterion remark (on by default).
  * Display summary - adds an average row at the bottom of the table (on by default).
  * Display email - includes the student's email address (off by default).
  * Display ID number - includes the student's ID number (off by default).
* Press **Submit** to generate the report.
* View a pivot table with:
  * All of the course students.
  * All of the activity grade criteria.
  * All of the students' grades and comments per criterion.
  * All of the students' overall grades and feedback.
* Download these results in CSV or Excel format.

**Note on feedback column:** the overall assignment feedback column is shown only for assignment activities. It is not shown for forum activities, which do not store overall feedback in the same way.

**Note on activity support:** only activities with an active marking guide grading method appear in the activity dropdown. If the dropdown is empty, no eligible activities have been set up in the course.

## Troubleshooting

**The activity dropdown is empty.**
No activities in the course have marking guide grading enabled. In the assignment (or forum) settings, set **Grading method** to **Marking Guide** under **Grade**, then define and publish a marking guide before returning to this report.

**The report shows no records.**
The selected activity has no enrolled students with the `mod/assign:submit` capability (i.e. no students enrolled in the course), or no marking guide grading area has been defined for the activity.

**The download produces an empty or corrupted file.**
Ensure no other output (debugging messages, PHP notices) is being sent before the download headers. Run the site with developer debugging enabled and check for notices on the report page.

**The report tab is not visible.**
The `gradereport/markingguide:view` capability is required. It is granted by default to teachers, editing teachers, and managers, and prevented for students. Check capability overrides at the course level if the tab is missing for a teacher.

## Privacy

This plugin displays grade data already held in Moodle. It does not store, export, or transmit any personal data itself.
