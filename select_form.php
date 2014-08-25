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
 * @package    report_markingguide
 * @copyright  2014 Learning Technology Services, www.lts.ie - Lead Developer: Karen Holland
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("$CFG->libdir/formslib.php");

class report_markingguide_select_form extends moodleform {

    public function definition() {
        global $CFG, $DB;

        $assignments = $DB->get_records_sql('SELECT {assign}.id AS assignmentid, {assign}.name AS assignment '.
            ' FROM {assign} JOIN {course} ON {course}.id = {assign}.course JOIN {grading_areas} '.
            ' ON {assign}.id = {grading_areas}.id WHERE {assign}.course = ? and {grading_areas}.activemethod = ?',
            array($this->_customdata['courseid'], 'guide'));

        $formarray = array(0 => 'Select');

        foreach ($assignments as $item) {
            $formarray[$item->assignmentid] = $item->assignment;
        }

        $mform =& $this->_form;

        // Check for any relevant assignments.
        if (count($assignments) == 0) {
            $mform->addElement ('html', get_string('err_noassignments', 'gradereport_markingguide'));
            return;
        }

        $mform->addElement ('select', 'assignmentid', get_string('selectassignment', 'gradereport_markingguide'), $formarray);
        $mform->setType('assignmentid', PARAM_INT);
        $mform->getElement('assignmentid')->setSelected(0);
        $mform->addElement ('advcheckbox', 'displaylevel', get_string('displaylevel', 'gradereport_markingguide'));
        $mform->getElement('displaylevel')->setValue(1);
        $mform->addElement ('advcheckbox', 'displayremark', get_string('displayremark', 'gradereport_markingguide'));
        $mform->getElement('displayremark')->setValue(1);
        $mform->addElement ('advcheckbox', 'displaysummary', get_string('displaysummary', 'gradereport_markingguide'));
        $mform->getElement('displaysummary')->setValue(1);
        $mform->addElement('hidden', 'id', $this->_customdata['courseid']);
        $mform->setType('id', PARAM_INT);
        $this->add_action_buttons(false, 'Go');
    }
}
