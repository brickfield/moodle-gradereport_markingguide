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
 *
 * @package    grade_report_markingguide
 * @copyright  2014 Learning Technology Services, www.lts.ie - Lead Developer: Karen Holland
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot.'/grade/report/lib.php');

class grade_report_markingguide extends grade_report {

    public $output;

    public function __construct($courseid, $gpr, $context, $page=null) {
        parent::__construct($courseid, $gpr, $context, $page);
        $this->course_grade_item = grade_item::fetch_course_item($this->courseid);
    }

    public function process_data($data) {
    }

    public function process_action($target, $action) {
    }

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

        $markingguidearray = array();

        // Step 2, find any markingguide related to assignment.
        $definitions = $DB->get_records_sql("select * from {grading_definitions} where areaid = ?", array($assignmentid));
        foreach ($definitions as $def) {
            $criteria = $DB->get_records_sql("select * from {gradingform_guide_criteria}".
                " where definitionid = ? order by sortorder", array($def->id));
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

            $queryarray = array(1, $assignmentid, $user->id);
            $userdata = $DB->get_records_sql($query, $queryarray);
            $data[$user->id] = array($fullname, $userdata);
        }

        if (count($data) == 0) {
            $output = get_string('err_norecords', 'gradereport_markingguide');
        } else {
            // Links for download.

            $linkurl = "index.php?id={$this->course->id}&amp;assignmentid={$this->assignmentid}&amp;".
                "displaylevel={$this->displaylevel}&amp;displayremark={$this->displayremark}&amp;format=";

            if ((!$this->csv)) {
                $output = '<ul class="markingguide-actions"><li><a href="'.$linkurl.'csv">'.
                    get_string('csvdownload', 'gradereport_markingguide').'</a></li>
                    <li><a href="'.$linkurl.'excelcsv">'.
                    get_string('excelcsvdownload', 'gradereport_markingguide').'</a></li></ul>';
            }

            // Put data into table.
            $output .= $this->display_table($data, $markingguidearray);
        }

        $this->output = $output;
        if (!$this->csv) {
            echo $output;
        }
    }

    public function display_table($data, $markingguidearray) {
        global $DB, $CFG;

        $csvoutput = "";
        $summaryarray = array();

        if (!$this->csv) {
            $output = html_writer::start_tag('div', array('class' => 'markingguide'));
            $table = new html_table();
            $table->head = array(get_string('student', 'gradereport_markingguide'));
            foreach ($markingguidearray as $key => $value) {
                $table->head[] = $markingguidearray[$key]['crit_desc'];
            }
            $table->head[] = get_string('grade', 'gradereport_markingguide');
            $table->data = array();
            $table->data[] = new html_table_row();
            $sep = ",";
            $line = "\n";
        } else {
            if ($this->excel) {
                print chr(0xFF).chr(0xFE);
                $sep = "\t".chr(0);
                $line = "\n".chr(0);
            } else {
                $sep = ",";
                $line = "\n";
            }
            // Add csv headers.
            $csvoutput .= $this->csv_quote(strip_tags(get_string('student', 'gradereport_markingguide')), $this->excel).$sep;
            foreach ($markingguidearray as $key => $value) {
                $csvoutput .= $this->csv_quote(strip_tags($markingguidearray[$key]['crit_desc']), $this->excel).$sep;
            }
            $csvoutput .= $this->csv_quote(strip_tags(get_string('grade', 'gradereport_markingguide')), $this->excel).$sep;
            $csvoutput .= $line;
        }

        $search = array("\r\n", "\n");

        foreach ($data as $values) {
            $row = new html_table_row();
            $cell = new html_table_cell();
            $cell->text = $values[0]; // Student name.
            $csvoutput .= $this->csv_quote(strip_tags($cell->text), $this->excel).$sep;
            $row->cells[] = $cell;
            $thisgrade = get_string('nograde', 'gradereport_markingguide');
            if (count($values[1]) == 0) { // Students with no marks, add fillers.
                foreach ($markingguidearray as $key => $value) {
                    $cell = new html_table_cell();
                    $cell->text = get_string('nograde', 'gradereport_markingguide');
                    $row->cells[] = $cell;
                    $csvoutput .= $this->csv_quote(strip_tags($cell->text), $this->excel).$sep;
                }
            }
            foreach ($values[1] as $value) {
                $cell = new html_table_cell();
                $cell->text .= round($value->score, 2);
                if ($this->displayremark) {
                    $cell->text .= " - ".str_replace($search, ' ', $value->remark);
                }
                $row->cells[] = $cell;
                $thisgrade = round($value->grade, 2); // Grade cell.

                if (!array_key_exists($value->criterionid, $summaryarray)) {
                    $summaryarray[$value->criterionid]["sum"] = 0;
                    $summaryarray[$value->criterionid]["count"] = 0;
                }
                $summaryarray[$value->criterionid]["sum"] += $value->score;
                $summaryarray[$value->criterionid]["count"]++;

                $csvoutput .= $this->csv_quote(strip_tags($cell->text), $this->excel).$sep;
            }

            $cell = new html_table_cell();
            $cell->text = $thisgrade; // Grade cell.
            if ($thisgrade != get_string('nograde', 'gradereport_markingguide')) {
                if (!array_key_exists("grade", $summaryarray)) {
                    $summaryarray["grade"]["sum"] = 0;
                    $summaryarray["grade"]["count"] = 0;
                }
                $summaryarray["grade"]["sum"] += $thisgrade;
                $summaryarray["grade"]["count"]++;
            }
            $row->cells[] = $cell;
            $csvoutput .= $this->csv_quote(strip_tags($thisgrade), $this->excel).$sep;
            $table->data[] = $row;
            $csvoutput .= $line;
        }

        // Summary row.
        if ($this->displaysummary) {
            $row = new html_table_row();
            $cell = new html_table_cell();
            $cell->text = get_string('summary', 'gradereport_markingguide');
            $row->cells[] = $cell;
            $csvoutput .= $this->csv_quote(strip_tags(get_string('summary', 'gradereport_markingguide')), $this->excel).$sep;
            foreach ($summaryarray as $sum) {
                $ave = round($sum["sum"] / $sum["count"], 2);
                $cell = new html_table_cell();
                $cell->text .= $ave;
                $csvoutput .= $this->csv_quote(strip_tags($ave), $this->excel).$sep;
                $row->cells[] = $cell;
            }
            $table->data[] = $row;
            $csvoutput .= $line;
        }

        if ($this->csv) {
            $output = $csvoutput;
        } else {
            $output .= html_writer::table($table);
            $output .= html_writer::end_tag('div');
        }

        return $output;
    }

    public function csv_quote($value, $excel) {
        if ($excel) {
            return core_text::convert('"'.str_replace('"', "'", $value).'"', 'UTF-8', 'UTF-16LE');
        } else {
            return '"'.str_replace('"', "'", $value).'"';
        }
    }

    private function get_moodle_grades() {
        global $DB, $CFG;

        $grades = $DB->get_records('grade_grades', array('itemid' => $this->course_grade_item->id), 'userid', 'userid, finalgrade');
        if (!is_array($grades)) {
            $grades = array();
        }

        $this->moodle_grades = array();

        if ($this->course_grade_item->gradetype == GRADE_TYPE_SCALE) {
            $config = get_config('grade_report_markingguide');
            $pgscale = new grade_scale(array('id' => $config->scale));
            $scaleitems = $pgscale->load_items();
            foreach ($this->moodle_students as $st) {
                if (isset($grades[$st->id])) {
                    $fg = (int)$grades[$st->id]->finalgrade;
                    if (isset($scaleitems[$fg - 1])) {
                        $this->moodle_grades[$st->id] = $scaleitems[$fg - 1];
                    } else {
                        $this->moodle_grades[$st->id] = null;
                    }
                } else {
                    $this->moodle_grades[$st->id] = null;
                }
            }
        } else {
            foreach ($this->moodle_students as $st) {
                if (isset($grades[$st->id])) {
                    $this->moodle_grades[$st->id] = grade_format_gradevalue($grades[$st->id]->finalgrade,
                                                                        $this->course_grade_item, true,
                                                                        $this->course_grade_item->get_displaytype(), null);
                } else {
                    $this->moodle_grades[$st->id] = null;
                }
            }
        }
    }
}
