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

$activityid      = optional_param('activityid', 0, PARAM_INT);
$displayremark   = optional_param('displayremark', 1, PARAM_INT);
$displaysummary  = optional_param('displaysummary', 1, PARAM_INT);
$displayidnumber = optional_param('displayidnumber', 1, PARAM_INT);
$displayemail    = optional_param('displayemail', 1, PARAM_INT);
$download        = optional_param('download', '', PARAM_ALPHA); // Set by flexible_table download button.
$courseid        = required_param('id', PARAM_INT); // Course id.

if (!$course = get_course($courseid)) {
    throw new moodle_exception(get_string('invalidcourseid', 'gradereport_markingguide'));
}

$PAGE->set_url(new moodle_url('/grade/report/markingguide/index.php', [
    'id'              => $courseid,
    'activityid'      => $activityid,
    'displayremark'   => $displayremark,
    'displaysummary'  => $displaysummary,
    'displayidnumber' => $displayidnumber,
    'displayemail'    => $displayemail,
]));

require_login($courseid);

$context = context_course::instance($course->id);

require_capability('gradereport/markingguide:view', $context);

$activityname    = '';
$displayfeedback = false;

// Set up the form.
$mform = new report_markingguide_select_form(null, ['courseid' => $courseid, 'activityid' => $activityid]);

// Only process the activity-select form when this is not a flexible_table download request.
// The download button submits a GET request with a 'download' param; skipping form
// processing on download requests ensures the request reaches init_table() intact.
if (empty($download) && ($formdata = $mform->get_data())) {
    $activityid = $formdata->activityid;
    $config = get_config('gradereport_markingguide');
    if (!empty($config->displayurlparams)) {
        $fullurl = new moodle_url('/grade/report/markingguide/index.php', (array)$formdata);
        redirect($fullurl);
    }
}

if ($activityid != 0) {
    $cm = get_fast_modinfo($courseid)->cms[$activityid];
    $activityname = format_string($cm->name, true, ['context' => $context]);
    $gradables = report::get_gradables();
    $displayfeedback = $gradables[$cm->modname]['showfeedback'] ?? false;
}

$gpr = new grade_plugin_return(['type' => 'report', 'plugin' => 'grader',
    'courseid' => $courseid]); // Return tracking object.
$report = new report(
    $courseid,
    $gpr,
    $context,
    null
);
$report->activityid      = $activityid;
$report->displayremark   = ($displayremark == 1);
$report->displaysummary  = ($displaysummary == 1);
$report->displayidnumber = ($displayidnumber == 1);
$report->displayemail    = ($displayemail == 1);
$report->activityname    = $activityname;
$report->displayfeedback = $displayfeedback;

// Initialise the flexible_table early - is_downloading() is then usable before any page
// HTML is output. Passing $download lets is_downloading() set the format and trigger
// start_document() (which sends file headers) before setup() or any page output runs.
$table = $report->init_table($download);

if (!$table->is_downloading()) {
    $PAGE->set_pagelayout('report');
    print_grade_page_head($COURSE->id, 'report', 'markingguide',
        get_string('pluginname', 'gradereport_markingguide') .
        $OUTPUT->help_icon('pluginname', 'gradereport_markingguide'));
    $mform->display();
    grade_regrade_final_grades($courseid);
}

$report->show($table);

if (!$table->is_downloading()) {
    echo $OUTPUT->footer();
}
