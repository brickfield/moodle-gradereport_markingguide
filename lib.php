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
 *  API functionality for marking guide report
 *
 * @package    gradereport_markingguide
 * @copyright  2014 Learning Technology Services, www.lts.ie - Lead Developer: Karen Holland
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/grade/report/lib.php');

/**
 * API functionality for marking guide report
 */
class grade_report_markingguide extends grade_report {

    /**
     * Holds output value
     *
     * @var mixed
     */
    public $output;

    /**
     * Initalization for marking guide report
     *
     * @param int $courseid
     * @param object $gpr
     * @param string $context
     * @param int|null $page
     */
    public function __construct($courseid, $gpr, $context, $page=null) {
        parent::__construct($courseid, $gpr, $context, $page);
        $this->course_grade_item = grade_item::fetch_course_item($this->courseid);
    }

    /**
     * Needed definition for grade_report
     *
     * @param array $data
     * @return void
     */
    public function process_data($data) {
    }

    /**
     * Needed definition for grade_report
     *
     * @param string $target
     * @param string $action
     * @return void
     */
    public function process_action($target, $action) {
    }

    /**
     * Gets information and generates grade report
     *
     * @return void
     */
    public function show() {
        global $DB, $CFG;

        $output = "";
        $assignmentid = $this->assignmentid;
        if ($assignmentid == 0) {
            return($output);
        } // Disabling all assignments option.

        // Step one, find all enrolled users to course.

        $coursecontext = context_course::instance($this->course->id);
        $users = get_enrolled_users($coursecontext, $withcapability = 'mod/assign:submit', $groupid = 0,
            $userfields = 'u.*', $orderby = 'u.lastname');
        $data = array();

        // Process relevant grading area id from assignmentid and courseid.
        $area = $DB->get_record_sql('select gra.id as areaid from {course_modules} cm'.
            ' join {context} con on cm.id=con.instanceid'.
            ' join {grading_areas} gra on gra.contextid = con.id'.
            ' where cm.module = ? and cm.course = ? and cm.instance = ? and gra.activemethod = ?',
            array(1, $this->course->id, $assignmentid, 'guide'));

        $markingguidearray = [];

        // Step 2, find any markingguide related to assignment.
        $definitions = $DB->get_records_sql("select * from {grading_definitions} where areaid = ?", array($area->areaid));
        foreach ($definitions as $def) {
            $criteria = $DB->get_records_sql("select * from {gradingform_guide_criteria}".
                " where definitionid = ? order by sortorder", [$def->id]);
            foreach ($criteria as $crit) {
                $markingguidearray[$crit->id]['crit_desc'] = $crit->shortname;
            }
        }

        foreach ($users as $user) {
            $fullname = fullname($user); // Get Moodle fullname.
            $query = "SELECT ggf.id, gd.id as defid, ag.userid, ag.grade, ggf.instanceid,".
                " ggf.criterionid, ggf.remark, ggf.score".
                " FROM {assign_grades} ag".
                " JOIN {grading_instances} gin".
                  " ON ag.id = gin.itemid".
                " JOIN {grading_definitions} gd".
                  " ON (gd.id = gin.definitionid )".
                " JOIN {gradingform_guide_fillings} ggf".
                  " ON (ggf.instanceid = gin.id)".
                " WHERE gin.status = ? and ag.assignment = ? and ag.userid = ?";

            $queryarray = [1, $assignmentid, $user->id];
            $userdata = $DB->get_records_sql($query, $queryarray);

            $query2 = "SELECT gig.id, gig.feedback".
                " FROM {grade_items} git".
                " JOIN {grade_grades} gig".
                " ON git.id = gig.itemid".
                " WHERE git.iteminstance = ? and gig.userid = ?";
            $feedback = $DB->get_records_sql($query2, [$assignmentid, $user->id]);
            $data[$user->id] = [$fullname, $user->email, $userdata, $feedback, $user->idnumber];
        }

        if (count($data) == 0) {
            $output = get_string('err_norecords', 'gradereport_markingguide');
        } else {
            // Links for download.
            $linkurl = "index.php?id={$this->course->id}&amp;assignmentid={$this->assignmentid}&amp;".
                "displayremark={$this->displayremark}&amp;displaysummary={$this->displaysummary}&amp;".
                "displayemail={$this->displayemail}&amp;displayidnumber={$this->displayidnumber}&amp;format=";

            if ((!$this->csv)) {
                $output = get_string('html_warning', 'gradereport_markingguide') .'<br/>'.
                    '<ul class="markingguide-actions"><li><a href="'.$linkurl.'csv">'.
                    get_string('csvdownload', 'gradereport_markingguide').'</a></li>
                    <li><a href="'.$linkurl.'excelcsv">'.
                    get_string('excelcsvdownload', 'gradereport_markingguide').'</a></li></ul>';

                // Put data into table.
                $output .= $this->display_table($data, $markingguidearray);
            } else {
                // Put data into array, not string, for csv download.
                $output = $this->display_table($data, $markingguidearray);
            }
        }

        $this->output = $output;
        if (!$this->csv) {
            echo $output;
        } else {
            if ($this->excel) {
                require_once("$CFG->libdir/excellib.class.php");

                $filename = "marking_{$this->assignmentname}.xls";
                $downloadfilename = clean_filename($filename);
                // Creating a workbook.
                $workbook = new MoodleExcelWorkbook("-");
                // Sending HTTP headers.
                $workbook->send($downloadfilename);
                // Adding the worksheet.
                $myxls = $workbook->add_worksheet($filename);

                $row = 0;
                // Running through data.
                foreach ($output as $value) {
                    $col = 0;
                    foreach ($value as $newvalue) {
                        $myxls->write_string($row, $col, $newvalue);
                        $col++;
                    }
                    $row++;
                }

                $workbook->close();
                exit;
            } else {
                require_once($CFG->libdir .'/csvlib.class.php');

                $filename = "marking_{$this->assignmentname}";
                $csvexport = new csv_export_writer();
                $csvexport->set_filename($filename);

                foreach ($output as $value) {
                    $csvexport->add_data($value);
                }
                $csvexport->download_file();

                exit;
            }
        }
    }

    /**
     * Displays the table for the grade report
     *
     * @param mixed $data
     * @param mixed $markingguidearray
     * @return void
     */
    public function display_table($data, $markingguidearray) {
        global $DB, $CFG;

        $summaryarray = [];
        $csvarray = [];

        $output = html_writer::start_tag('div', ['class' => 'markingguide']);
        $table = new html_table();
        $table->head = array(get_string('student', 'gradereport_markingguide'));
        if ($this->displayidnumber) {
            $table->head[] = get_string('studentid', 'gradereport_markingguide');
        }
        if ($this->displayemail) {
            $table->head[] = get_string('studentemail', 'gradereport_markingguide');
        }
        foreach ($markingguidearray as $key => $value) {
            $table->head[] = $markingguidearray[$key]['crit_desc'];
        }
        if ($this->displayremark) {
            $table->head[] = get_string('feedback', 'gradereport_markingguide');
        }
        $table->head[] = get_string('grade', 'gradereport_markingguide');
        $csvarray[] = $table->head;
        $table->data = [];
        $table->data[] = new html_table_row();

        foreach ($data as $key => $values) {
            $csvrow = [];
            $row = new html_table_row();
            $cell = new html_table_cell();
            $cell->text = $values[0]; // Student name.
            $csvrow[] = $values[0];
            $row->cells[] = $cell;
            if ($this->displayidnumber) {
                $cell = new html_table_cell();
                $cell->text = $values[4]; // Student ID number.
                $row->cells[] = $cell;
                $csvrow[] = $values[4];
            }
            if ($this->displayemail) {
                $cell = new html_table_cell();
                $cell->text = $values[1]; // Student email.
                $row->cells[] = $cell;
                $csvrow[] = $values[1];
            }
            $thisgrade = get_string('nograde', 'gradereport_markingguide');
            if (count($values[2]) == 0) { // Students with no marks, add fillers.
                foreach ($markingguidearray as $key => $value) {
                    $cell = new html_table_cell();
                    $cell->text = get_string('nograde', 'gradereport_markingguide');
                    $row->cells[] = $cell;
                    $csvrow[] = $thisgrade;
                }
            }
            foreach ($values[2] as $value) {
                $cell = new html_table_cell();
                $cell->text .= "<div class=\"markingguide_marks\">Mark:&nbsp;".round($value->score, 2)."</div>";
                $csvtext = round($value->score, 2);
                if ($this->displayremark) {
                    $cell->text .= $value->remark;
                    $csvtext .= " - ".$value->remark;
                }
                $row->cells[] = $cell;
                $thisgrade = round($value->grade, 2); // Grade cell.

                if (!array_key_exists($value->criterionid, $summaryarray)) {
                    $summaryarray[$value->criterionid]["sum"] = 0;
                    $summaryarray[$value->criterionid]["count"] = 0;
                }
                $summaryarray[$value->criterionid]["sum"] += $value->score;
                $summaryarray[$value->criterionid]["count"]++;

                $csvrow[] = $csvtext;
            }

            if ($this->displayremark) {
                $cell = new html_table_cell();
                if (is_object($values[3])) {
                    $cell->text = strip_tags($values[3]->feedback);
                } // Feedback cell.
                if (empty($cell->text)) {
                    $cell->text = get_string('nograde', 'gradereport_markingguide');
                }
                $row->cells[] = $cell;
                $csvrow[] = $cell->text;
                $summaryarray["feedback"]["sum"] = get_string('feedback', 'gradereport_markingguide');
            }

            $cell = new html_table_cell();
            $cell->text = $thisgrade; // Grade cell.
            $csvrow[] = $cell->text;
            if ($thisgrade != get_string('nograde', 'gradereport_markingguide')) {
                if (!array_key_exists("grade", $summaryarray)) {
                    $summaryarray["grade"]["sum"] = 0;
                    $summaryarray["grade"]["count"] = 0;
                }
                $summaryarray["grade"]["sum"] += $thisgrade;
                $summaryarray["grade"]["count"]++;
            }
            $row->cells[] = $cell;
            $table->data[] = $row;
            $csvarray[] = $csvrow;
        }

        // Summary row.
        if ($this->displaysummary) {
            $row = new html_table_row();
            $cell = new html_table_cell();
            $cell->text = get_string('summary', 'gradereport_markingguide');
            $row->cells[] = $cell;
            $csvsummaryrow = [get_string('summary', 'gradereport_markingguide')];
            if ($this->displayidnumber) { // Adding placeholder cells.
                $cell = new html_table_cell();
                $cell->text = " ";
                $row->cells[] = $cell;
                $csvsummaryrow[] = $cell->text;
            }
            if ($this->displayemail) { // Adding placeholder cells.
                $cell = new html_table_cell();
                $cell->text = " ";
                $row->cells[] = $cell;
                $csvsummaryrow[] = $cell->text;
            }
            foreach ($summaryarray as $sum) {
                $cell = new html_table_cell();
                if ($sum["sum"] == get_string('feedback', 'gradereport_markingguide')) {
                    $cell->text = " ";
                } else {
                    $cell->text = round($sum["sum"] / $sum["count"], 2);
                }
                $row->cells[] = $cell;
                $csvsummaryrow[] = $cell->text;
            }
            $table->data[] = $row;
            $csvarray[] = $csvsummaryrow;
        }

        if ($this->csv) {
            $output = $csvarray;
        } else {
            $output .= html_writer::table($table);
            $output .= html_writer::end_tag('div');
        }

        return $output;
    }
}
