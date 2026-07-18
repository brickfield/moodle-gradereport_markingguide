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

use flexible_table;
use grade_report;
use grade_item;
use moodle_url;
use gradereport_markingguide\data;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/grade/report/lib.php');
/**
 * Provides the grade report for marking guides.
 *
 * @package    gradereport_markingguide
 * @copyright  2021 onward Brickfield Education Labs Ltd, https://www.brickfield.ie
 * @author     2021 Clayton Darlington <clayton@brickfieldlabs.ie>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report extends grade_report {
    /** @var grade_item Grade item. */
    public $coursegradeitem;

    /** @var int Activity id. */
    public $activityid;

    /** @var string Activity name. */
    public $activityname;

    /** @var bool Display remark. */
    public $displayremark;

    /** @var bool Display summary. */
    public $displaysummary;

    /** @var bool Display email. */
    public $displayemail;

    /** @var bool Display id number. */
    public $displayidnumber;

    /** @var bool Display feedback. */
    public $displayfeedback;

    /**
     * Initialisation for marking guide report.
     *
     * @param int $courseid
     * @param object $gpr
     * @param string $context
     * @param int|null $page
     */
    public function __construct($courseid, $gpr, $context, $page = null) {
        parent::__construct($courseid, $gpr, $context, $page);
        $this->coursegradeitem = grade_item::fetch_course_item($this->courseid);
    }

    /**
     * Needed definition for grade_report.
     *
     * @param array $data
     * @return void
     */
    public function process_data($data) {
    }

    /**
     * Needed definition for grade_report.
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
     * Initialise, configure, and set up the flexible_table instance.
     *
     * Resolves the full column list - including dynamic marking guide criterion columns -
     * and calls is_downloading() then setup() before returning. This means is_downloading()
     * is usable immediately after this call, allowing index.php to suppress page HTML on
     * download requests before any output is sent.
     *
     * In Moodle 5.x, setup() no longer reads the download param from the request.
     * is_downloading() must be called explicitly with the format string before setup() so
     * that is_downloading() returns the format correctly when index.php checks it.
     *
     * @param string $download Download format string from the request (e.g. 'csv', 'excel').
     *                         Empty string means no download this request.
     * @return flexible_table
     */
    public function init_table(string $download = ''): flexible_table {
        global $DB;

        $columns = ['student'];
        $headers = [get_string('student', 'gradereport_markingguide')];

        if ($this->displayidnumber) {
            $columns[] = 'idnumber';
            $headers[] = get_string('studentid', 'gradereport_markingguide');
        }
        if ($this->displayemail) {
            $columns[] = 'email';
            $headers[] = get_string('studentemail', 'gradereport_markingguide');
        }

        // Resolve marking guide criterion columns now so setup() can be called before any
        // output. On a download request the column headers use the plain criterion_label
        // string; on HTML display they use criterion_label_break (which contains a <br />).
        // flexible_table handles this cleanly: is_downloading() is already set by the time
        // we build headers here.
        if ($this->activityid != 0) {
            $areasql = "SELECT gra.id as areaid FROM {course_modules} cm
                          JOIN {context} con ON cm.id = con.instanceid
                          JOIN {grading_areas} gra ON gra.contextid = con.id
                         WHERE cm.course = ? AND cm.id = ? AND gra.activemethod = ?";
            $area = $DB->get_record_sql($areasql, [$this->courseid, $this->activityid, 'guide']);

            if ($area) {
                $critsql = "SELECT crit.id, crit.shortname, crit.maxscore
                              FROM {grading_definitions} def
                              JOIN {gradingform_guide_criteria} crit ON crit.definitionid = def.id
                             WHERE def.areaid = ?
                             ORDER BY crit.sortorder";
                $criteria = $DB->get_records_sql($critsql, [$area->areaid]);

                $labelstring = empty($download) ? 'criterion_label_break' : 'criterion_label';
                foreach ($criteria as $crit) {
                    $columns[] = 'criterion_' . $crit->id;
                    $headers[] = get_string($labelstring, 'gradereport_markingguide', (object)[
                        'crit_desc' => format_string($crit->shortname, true, ['context' => $this->context]),
                        'max_score' => round($crit->maxscore, 2),
                    ]);
                }
            }
        }

        if ($this->displayremark && $this->displayfeedback) {
            $columns[] = 'feedback';
            $headers[] = get_string('feedback', 'gradereport_markingguide');
        }
        $columns[] = 'grade';
        $headers[] = get_string('grade', 'gradereport_markingguide');

        $table = new flexible_table('gradereport-markingguide-' . $this->activityid);
        $table->define_baseurl(new moodle_url('/grade/report/markingguide/index.php', [
            'id'              => $this->courseid,
            'activityid'      => $this->activityid,
            'displayremark'   => (int)$this->displayremark,
            'displaysummary'  => (int)$this->displaysummary,
            'displayemail'    => (int)$this->displayemail,
            'displayidnumber' => (int)$this->displayidnumber,
        ]));
        $table->set_attribute('class', 'markingguide generaltable');
        $table->set_attribute('summary', get_string('pluginname', 'gradereport_markingguide') . ': ' . $this->activityname);
        $table->sortable(false);
        $table->collapsible(false);
        $table->show_download_buttons_at([TABLE_P_BOTTOM]);

        // In Moodle 5.x, is_downloading() is the correct way to both mark the table as
        // downloadable and signal the active download format. Must be called before setup()
        // so that is_downloading() returns the format string correctly when index.php checks
        // it to decide whether to suppress page output.
        $tmpcourse = get_fast_modinfo($this->courseid)->get_course();
        $filename = clean_filename(($this->activityname ?: 'markingguide') . '_' . $tmpcourse->shortname);
        $table->is_downloading($download, $filename, get_string('pluginname', 'gradereport_markingguide'));

        $table->define_columns($columns);
        $table->define_headers($headers);
        $table->define_header_column('student');
        $table->setup();

        return $table;
    }

    /**
     * Generate and display the marking guide report.
     *
     * @param flexible_table $table Configured table instance from init_table().
     * @return void
     */
    public function show(flexible_table $table): void {
        global $OUTPUT;

        $activityid = $this->activityid;
        if ($activityid == 0) {
            return;
        }

        $users     = data::get_enrolled($this->courseid);
        $area      = data::get_grading_areas($activityid, $this->courseid);
        $markingguide = data::find_marking_guide($area);

        if (empty($users)) {
            if (!$table->is_downloading()) {
                echo $OUTPUT->notification(get_string('err_norecords', 'gradereport_markingguide'));
            }
            $table->finish_output();
            return;
        }

        $this->display_table($table, $users, $markingguide);
    }

    /**
     * Prepare a raw user-supplied value for output in a table cell.
     *
     * flexible_table does not escape the content it is given - get_row_cells_html()
     * passes it to html_writer::tag(), which escapes attributes only - so any value
     * placed in a cell must be made safe here.
     *
     * @param string $value The raw value.
     * @param bool $downloading Whether the table is being downloaded rather than displayed.
     * @return string The value, made safe for the relevant output format.
     */
    protected function format_cell_value(string $value, bool $downloading): string {
        if ($downloading) {
            return self::neutralise_formula($value);
        }
        return s($value);
    }

    /**
     * Prefix a value with an apostrophe if a spreadsheet would treat it as a formula.
     *
     * Excel and LibreOffice evaluate a cell whose first character is =, +, - or @.
     * The check ignores leading whitespace, because spreadsheets do too - a payload
     * starting with a space or tab is still evaluated, but would defeat a naive test
     * of the first character.
     *
     * @param string $value The cell value.
     * @return string The value, prefixed if it would otherwise be evaluated.
     */
    protected static function neutralise_formula(string $value): string {
        $trimmed = ltrim($value);
        if ($trimmed !== '' && strpos('=+-@', $trimmed[0]) !== false) {
            return "'" . $value;
        }
        return $value;
    }

    /**
     * Populate and finish the flexible_table with user data.
     *
     * @param flexible_table $table
     * @param array $users
     * @param array $markingguide
     * @return void
     */
    private function display_table(flexible_table $table, array $users, array $markingguide): void {
        $summaryarray = [];
        $downloading  = $table->is_downloading();

        foreach ($users as $user) {
            $userdata = data::populate_user_info($user, $this->activityid, $this->courseid);
            $row = [];

            $row[] = $this->format_cell_value((string)$userdata['fullname'], $downloading);

            if ($this->displayidnumber) {
                $row[] = $this->format_cell_value((string)$user->idnumber, $downloading);
            }
            if ($this->displayemail) {
                $row[] = $this->format_cell_value((string)$user->email, $downloading);
            }

            $thisgrade = get_string('nograde', 'gradereport_markingguide');

            if (count($userdata['data']) == 0) {
                // No marks yet - fill criterion columns with nograde placeholder.
                $row = array_merge($row, array_fill(0, count($markingguide), $thisgrade));
            }

            foreach ($userdata['data'] as $value) {
                $critgrade = get_string('criterion_grade', 'gradereport_markingguide', round($value->score, 2));

                if ($downloading) {
                    // Plain text for download: score and optional remark separated by a dash.
                    // Tags are stripped from the remark, as download_help documents. The
                    // formula check is applied to the assembled cell rather than to the
                    // remark alone: a spreadsheet evaluates a cell based on its own first
                    // character, so neutralising a value that lands mid-cell does nothing.
                    $cell = (string)round($value->score, 2);
                    if ($this->displayremark) {
                        $cell .= ' - ' . strip_tags($value->remark);
                    }
                    $cell = self::neutralise_formula($cell);
                } else {
                    // HTML display: score in a div, remark below if requested.
                    $cell = '<div class="markingguide_marks">' . $critgrade . '</div>';
                    if ($this->displayremark) {
                        $cell .= s($value->remark);
                    }
                }
                $row[] = $cell;

                $thisgrade = round($value->grade, 2);

                if (!array_key_exists($value->criterionid, $summaryarray)) {
                    $summaryarray[$value->criterionid]['sum']   = 0;
                    $summaryarray[$value->criterionid]['count'] = 0;
                }
                $summaryarray[$value->criterionid]['sum']   += $value->score;
                $summaryarray[$value->criterionid]['count']++;
            }

            if ($this->displayremark && $this->displayfeedback) {
                $feedback = '';
                if (is_object($userdata['feedback']) && !empty($userdata['feedback']->feedback)) {
                    // Stripping tags is sufficient for the HTML path - the value is cell text,
                    // not an attribute, so with no tags left there is nothing to execute. It is
                    // deliberately not passed through s() as well, because the source is
                    // FORMAT_HTML and double-encoding would display raw entities to the user.
                    $feedback = strip_tags($userdata['feedback']->feedback);
                    if ($downloading) {
                        $feedback = self::neutralise_formula($feedback);
                    }
                }
                $row[] = $feedback ?: get_string('nograde', 'gradereport_markingguide');
                $summaryarray['feedback']['sum'] = get_string('feedback', 'gradereport_markingguide');
            }

            // A null feedback object comes back from populate_user_info() when the activity
            // has no grade item for this user, so the grade string is guarded, not assumed.
            // The str_grade value is already formatted by core (format_string is applied to
            // scale names), so it is passed through unescaped to avoid double-encoding.
            $row[] = is_object($userdata['feedback'])
                ? $userdata['feedback']->str_grade
                : get_string('nograde', 'gradereport_markingguide');

            if ($thisgrade !== get_string('nograde', 'gradereport_markingguide')) {
                if (!array_key_exists('grade', $summaryarray)) {
                    $summaryarray['grade']['sum']   = 0;
                    $summaryarray['grade']['count'] = 0;
                }
                $summaryarray['grade']['sum']   += $thisgrade;
                $summaryarray['grade']['count']++;
            }

            $table->add_data($row);
        }

        // Summary row.
        if ($this->displaysummary) {
            $summaryrow = [get_string('summary', 'gradereport_markingguide')];
            if ($this->displayidnumber) {
                $summaryrow[] = '';
            }
            if ($this->displayemail) {
                $summaryrow[] = '';
            }
            foreach ($summaryarray as $sum) {
                if ($sum['sum'] === get_string('feedback', 'gradereport_markingguide')) {
                    $summaryrow[] = '';
                } else {
                    $summaryrow[] = round($sum['sum'] / $sum['count'], 2);
                }
            }
            $table->add_data($summaryrow);
        }

        $table->finish_output();
    }
}
