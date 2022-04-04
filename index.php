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
 * Gradebook marking guide report
 *
 * @package    gradereport_markingguide
 * @copyright  2014 Learning Technology Services, www.lts.ie - Lead Developer: Karen Holland
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use gradereport_markingguide\report;
require_once('../../../config.php');
require_once($CFG->libdir .'/gradelib.php');
require_once($CFG->dirroot.'/grade/lib.php');
require_once("select_form.php");

$assignmentid = optional_param('assignmentid', 0, PARAM_INT);
$displayremark = optional_param('displayremark', 1, PARAM_INT);
$displaysummary = optional_param('displaysummary', 1, PARAM_INT);
$displayidnumber = optional_param('displayidnumber', 1, PARAM_INT);
$displayemail = optional_param('displayemail', 1, PARAM_INT);
$format = optional_param('format', '', PARAM_ALPHA);
$courseid = required_param('id', PARAM_INT);// Course id.

if (!$course = $DB->get_record('course', ['id' => $courseid])) {
    throw new moodle_exception(get_string('invalidcourseid', 'grade_markingguide'));
}

// CSV format.
$excel = $format == 'excelcsv';
$csv = $format == 'csv' || $excel;

if (!$csv) {
    $PAGE->set_url(new moodle_url('/grade/report/markingguide/index.php', ['id' => $courseid]));
}

require_login($courseid);
if (!$csv) {
    $PAGE->set_pagelayout('report');
}

$context = context_course::instance($course->id);

require_capability('gradereport/markingguide:view', $context);

$assignmentname = '';

// Set up the form.
$mform = new report_markingguide_select_form(null, array('courseid' => $courseid));

// Did we get anything from the form?
if ($formdata = $mform->get_data()) {
    // Get the users markingguide.
    $assignmentid = $formdata->assignmentid;
}

if ($assignmentid != 0) {
    $assignment = $DB->get_record_sql('SELECT name FROM {assign} WHERE id = ? limit 1', [$assignmentid]);
    $assignmentname = format_string($assignment->name, true, ['context' => $context]);
}

if (!$csv) {
    print_grade_page_head($COURSE->id, 'report', 'markingguide',
        get_string('pluginname', 'gradereport_markingguide') .
        $OUTPUT->help_icon('pluginname', 'gradereport_markingguide'));

    // Display the form.
    $mform->display();

    grade_regrade_final_grades($courseid); // First make sure we have proper final grades.
}

$gpr = new grade_plugin_return(['type' => 'report', 'plugin' => 'grader',
    'courseid' => $courseid]); // Return tracking object.
$report = new report($courseid, $gpr, $context); // Initialise the grader report object.
$report->assignmentid = $assignmentid;
$report->format = $format;
$report->excel = $format == 'excelcsv';
$report->csv = $format == 'csv' || $report->excel;
$report->displayremark = ($displayremark == 1);
$report->displaysummary = ($displaysummary == 1);
$report->displayidnumber = ($displayidnumber == 1);
$report->displayemail = ($displayemail == 1);
$report->assignmentname = $assignmentname;

$table = $report->show();
echo $table;
echo $OUTPUT->footer();
