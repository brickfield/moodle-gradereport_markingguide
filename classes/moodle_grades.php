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

/**
 * Provides extra moodle grade functionality.
 *
 * @package    gradereport_markingguide
 * @copyright  2021 onward Brickfield Education Labs Ltd, https://www.brickfield.ie
 * @author     2021 Karen Holland <karen@brickfieldlabs.ie>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace grade_report\markingguide;
use grade_scale;

/**
 * moodle grade helper function
 */
class moodle_grade {

    /**
     * Get moodle grades
     *
     * @return void
     */
    private function get_moodle_grades() {
        global $DB, $CFG;

        $grades = $DB->get_records('grade_grades', ['itemid' => $this->course_grade_item->id], 'userid', 'userid, finalgrade');
        if (!is_array($grades)) {
            $grades = [];
        }

        $this->moodle_grades = [];

        if ($this->course_grade_item->gradetype == GRADE_TYPE_SCALE) {
            $config = get_config('grade_report_markingguide');
            $pgscale = new grade_scale(['id' => $config->scale]);
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
