<?php
// This file is part of the gradereport markingguide plugin
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
namespace gradereport_markingguide;

use grade_report;
use html_writer;
use html_table;
use html_table_cell;
use html_table_row;
use moodle_url;
use grade_item;
use MoodleExcelWorkbook;
use csv_export_writer;
use gradereport_markingguide\data;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/grade/report/lib.php');
/**
 * Provides the grade report for marking guides.
 *
 * @package    gradereport_markingguide
 * @copyright  2021 onward Brickfield Education Labs Ltd, https://www.brickfield.ie
 * @author     2021 Clayton Darlington <clayton@brickfieldlabs.ie>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report extends grade_report {
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
     * Retrieve gradables const.
     *
     * @return array
     */
    public static function get_gradables() {
        return data::GRADABLES;
    }

    /**
     * Generate and display the grading report
     *
     * @return mixed
     */
    public function show() {
        global $CFG, $OUTPUT;

        $activityid = $this->activityid;
        if ($activityid == 0) {
            return($this->output);
        } // Disabling all activities option.

        $users = data::get_enrolled($this->courseid);
        $data = [];

        $gradingarea = data::get_grading_areas($activityid, $this->courseid);

        $markingguide = data::find_marking_guide($gradingarea);

        foreach ($users as $user) {
            $userdata = data::populate_user_info($user, $activityid, $this->courseid);
            $data[$user->id] = [$userdata['fullname'], $user->email, $userdata['data'], $userdata['feedback'], $user->idnumber];
        }

        if (count($data) == 0) {
            $output = get_string('err_norecords', 'gradereport_markingguide');
        } else {
            $csvlink = new moodle_url('/grade/report/markingguide/index.php', [
                'id' => $this->course->id,
                'activityid' => $this->activityid,
                'displayremark' => $this->displayremark,
                'displaysummary' => $this->displaysummary,
                'displayemail' => $this->displayemail,
                'displayidnumber' => $this->displayidnumber,
                'format' => 'csv',
            ]);

            $xlsxlink = new moodle_url('/grade/report/markingguide/index.php', [
                'id' => $this->course->id,
                'activityid' => $this->activityid,
                'displayremark' => $this->displayremark,
                'displaysummary' => $this->displaysummary,
                'displayemail' => $this->displayemail,
                'displayidnumber' => $this->displayidnumber,
                'format' => 'excelcsv',
            ]);

            // Links for download.
            if ((!$this->csv)) {
                $output = html_writer::start_tag('ul', ['class' => 'markingguide-actions']);
                $output .= html_writer::start_tag('li');
                $output .= html_writer::link($csvlink, get_string('csvdownload', 'gradereport_markingguide'));
                $output .= '&nbsp;' . $OUTPUT->help_icon('download', 'gradereport_markingguide');
                $output .= html_writer::end_tag('il');
                $output .= html_writer::start_tag('li');
                $output .= html_writer::link($xlsxlink, get_string('excelcsvdownload', 'gradereport_markingguide'));
                $output .= '&nbsp;' . $OUTPUT->help_icon('download', 'gradereport_markingguide');
                $output .= html_writer::end_tag('il');
                $output .= html_writer::end_tag('ul');

                // Put data into table.
                $output .= $this->display_report($data, $markingguide, false);
            } else {
                // Put data into array, not string, for csv download.
                $output = $this->display_report($data, $markingguide, true);
            }
        }
        if (!$this->csv) {
            echo $output;
        } else {
            if ($this->excel) {
                require_once("$CFG->libdir/excellib.class.php");

                $filename = get_string('filename', 'gradereport_markingguide', $this->activityname) . ".xls";
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

                $filename = get_string('filename', 'gradereport_markingguide', $this->activityname);
                $filename = clean_filename($filename);
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
     * Display the table.
     *
     * @param array $data
     * @param array $markingguide
     * @param bool $csv
     * @return void
     */
    private function display_report($data, $markingguide, $csv) {
        $summaryarray = [];
        $csvarray = [];

        $output = html_writer::start_tag('div', ['class' => 'markingguide']);
        $table = new html_table();

        $table->head = [get_string('student', 'gradereport_markingguide')];
        // Add the extra fields if needed.
        if ($this->displayidnumber) {
            $table->head[] = get_string('studentid', 'gradereport_markingguide');
        }
        if ($this->displayemail) {
            $table->head[] = get_string('studentemail', 'gradereport_markingguide');
        }
        foreach ($markingguide as $key => $value) {
            if ($csv) {
                $table->head[] = get_string('criterion_label', 'gradereport_markingguide', (object)$value);
            } else {
                $table->head[] = get_string('criterion_label_break', 'gradereport_markingguide', (object)$value);
            }
        }
        if ($this->displayremark && $this->displayfeedback) {
            $table->head[] = get_string('feedback', 'gradereport_markingguide');
        }

        $table->head[] = get_string('grade', 'gradereport_markingguide');
        $csvarray[] = $table->head;
        $table->data = [];

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
                foreach ($markingguide as $key => $value) {
                    $cell = new html_table_cell();
                    $cell->text = get_string('nograde', 'gradereport_markingguide');
                    $row->cells[] = $cell;
                    $csvrow[] = $thisgrade;
                }
            }
            // Handle the marking guide criteria grades.
            foreach ($values[2] as $value) {
                $cell = new html_table_cell();
                $critgrade = get_string('criterion_grade', 'gradereport_markingguide', round($value->score, 2));
                $cell->text .= "<div class=\"markingguide_marks\">" . $critgrade . "</div>";
                $csvtext = round($value->score, 2);

                // Display the remark if user asks to.
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
                // Sum the grade to for the final column.
                $summaryarray[$value->criterionid]["sum"] += $value->score;
                $summaryarray[$value->criterionid]["count"]++;

                $csvrow[] = $csvtext;
            }

            if ($this->displayremark && $this->displayfeedback) {
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
            $cell->text = $values[3]->str_grade; // Grade for display.
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
