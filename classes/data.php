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
use context_course;

/**
 * Provides data gathering and manipulation functionality for a marking guide report.
 *
 * @package    gradereport_markingguide
 * @copyright  2021 onward Brickfield Education Labs Ltd, https://www.brickfield.ie
 * @author     2021 Clayton Darlington <clayton@brickfieldlabs.ie>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class data {

    /**
     * Get all the enrolled users in the course
     *
     * @param int $courseid
     * @return array
     */
    public static function get_enrolled($courseid) {
        $coursecontext = context_course::instance($courseid);
        $users = get_enrolled_users($coursecontext, $withcapability = 'mod/assign:submit', $groupid = 0,
            $userfields = 'u.*', $orderby = 'u.lastname');

        return $users;
    }

    /**
     * Get the grading areas for an assignment
     *
     * @param mixed $assignmentid
     * @param int $courseid
     * @return array
     */
    public static function get_grading_areas($assignmentid, $courseid) {
        global $DB;

        $area = $DB->get_record_sql('select gra.id as areaid from {course_modules} cm'.
        ' join {context} con on cm.id=con.instanceid'.
        ' join {grading_areas} gra on gra.contextid = con.id'.
        ' where cm.module = ? and cm.course = ? and cm.instance = ? and gra.activemethod = ?',
        [1, $courseid, $assignmentid, 'guide']);

        return $area;
    }

    /**
     * Return the relevant marking guide assignments
     *
     * @param object $area
     * @return array
     */
    public static function find_marking_guide($area) {
        global $DB;
        $markingguidearray = [];

        $definitions = $DB->get_records_sql("select * from {grading_definitions} where areaid = ?", [$area->areaid]);
        foreach ($definitions as $def) {
            $criteria = $DB->get_records_sql("select * from {gradingform_guide_criteria}".
                " where definitionid = ? order by sortorder", [$def->id]);
            foreach ($criteria as $crit) {
                $markingguidearray[$crit->id]['crit_desc'] = $crit->shortname;
            }
        }
        return $markingguidearray;
    }

    /**
     * Generate all the grade data for a user
     *
     * @param object $user
     * @param int $assignmentid
     * @param int $courseid
     * @return array
     */
    public static function populate_user_info($user, $assignmentid, $courseid) {
        global $DB;
        $userdata = [];
        $userdata['fullname'] = fullname($user); // Get Moodle fullname.
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
        $userdata['data'] = $DB->get_records_sql($query, $queryarray);

        $fullgrade = \grade_get_grades($courseid, 'mod', 'assign', $assignmentid, [$user->id]);
        $feedback = $fullgrade->items[0]->grades[$user->id];
        $userdata['feedback'] = $feedback;
        return $userdata;
    }
}
