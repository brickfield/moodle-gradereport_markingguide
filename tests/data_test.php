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
 * Unit tests for grade/report/markingguide/data.php
 *
 * @package    gradereport_markingguide
 * @copyright  2021 onward Brickfield Education Labs Ltd, https://www.brickfield.ie
 * @author     2021 Clayton Darlington <clayton@brickfieldlabs.ie>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use gradereport_markingguide\data;

/**
 * A test class used to test grade_report, the abstract grade report parent class
 */
class data_test extends advanced_testcase {

    /**
     * Test get_enrolled function
     */
    public function test_get_enrolled() {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $student1 = $this->getDataGenerator()->create_and_enrol($course);
        $student2 = $this->getDataGenerator()->create_and_enrol($course);
        $student3 = $this->getDataGenerator()->create_and_enrol($course);

        $enrolled = data::get_enrolled($course->id);
        $this->assertNotEmpty($enrolled);
    }

    /**
     * Test the get_grade_area function
     */
    public function test_grading_areas() {
        $this->resetAfterTest(true);
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course]);

        // Get the ID for the grading_area from course_modules where con.instanceid = cm.id.
        // User that ID to generate a grading_area?
        $cm = $DB->get_record('course_modules', ['instance' => $assign->id]);
        $context = $DB->get_record('context', ['instanceid' => $cm->id]);

        // Generate the gradearea directly with the right info.
        $gradeareadata = new \stdClass;
        $gradeareadata->contextid = $context->id;
        $gradeareadata->component = 'mod_assign';
        $gradeareadata->areaname = 'submissions';
        $gradeareadata->activemethod = 'guide';

        $gradearea = $DB->insert_record('grading_areas', $gradeareadata);

        // Find the grade area.
        $data = data::get_grading_areas($cm->id, $course->id);
        $this->assertNotEmpty($data);
    }

    public function test_find_marking_guide() {
        $this->resetAfterTest(true);

        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course]);

        // Get the ID for the grading_area from course_modules where con.instanceid = cm.id.
        // Use that ID to generate a grading_area?
        $cm = $DB->get_record('course_modules', ['instance' => $assign->id]);
        $context = $DB->get_record('context', ['instanceid' => $cm->id]);

        // Generate the gradearea directly with the right info.
        $gradeareadata = new \stdClass;
        $gradeareadata->contextid = $context->id;
        $gradeareadata->component = 'mod_assign';
        $gradeareadata->areaname = 'submissions';
        $gradeareadata->activemethod = 'guide';

        $gradearea = $DB->insert_record('grading_areas', $gradeareadata);

        // Find the grade area.
        $area = data::get_grading_areas($cm->id, $course->id);

        // Generate and store a grading definition for the area.
        $definition = new \stdClass;
        $definition->areaid = $area->areaid;
        $definition->timecreated = time();
        $definition->timemodified = time();
        $definition->usercreated = $student->id;
        $definition->usermodified = $student->id;
        $gradingdef = $DB->insert_record('grading_definitions', $definition);

        // Generate the guide criteria.
        $criteria = new \stdClass;
        $criteria->definitionid = $gradingdef;
        $criteria->sortorder = 1;
        $criteria->maxscore = 100;
        $criteria->shortname = "This is a temp shortname";
        $criteriarecord = $DB->insert_record('gradingform_guide_criteria', $criteria);

        $markingguides = data::find_marking_guide($area);
        $this->assertNotEmpty($markingguides);
    }

    /**
     * Test the populate_user_info function
     */
    public function test_populate_user_info() {
        $this->resetAfterTest();
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course]);

        $cm = $DB->get_record('course_modules', ['instance' => $assign->id]);

        // Create a grade for the assignment.
        $assignmentgrade = new \stdClass;
        $assignmentgrade->assignment = $assign->id;
        $assignmentgrade->userid = $student->id;
        $assignmentgrade->timecreated = time();
        $assignmentgrade->timemodified = time();
        $assignmentgrade->grader = $student->id;
        $assignmentgrade->grade = 100;
        $assignmentgrade = $DB->insert_record('assign_grades', $assignmentgrade);

        $definition = new \stdClass;
        $definition->areaid = 1;
        $definition->timecreated = time();
        $definition->timemodified = time();
        $definition->usercreated = $student->id;
        $definition->usermodified = $student->id;
        $gradingdef = $DB->insert_record('grading_definitions', $definition);

        $gradeinstance = new \stdClass;
        $gradeinstance->id = $assign->id;
        $gradeinstance->definitionid = $gradingdef;
        $gradeinstance->raterid = 1;
        $gradeinstance->itemid = 1;
        $gradeinstance->status = 3;
        $gradeinstance->timemodified = time();
        $gradeinstance = $DB->insert_record('grading_instances', $gradeinstance);

        $gradefilling = new \stdClass;
        $gradefilling->instanceid = $gradeinstance;
        $gradefilling->criterionid = 1;
        $gradefilling->remark = "This is a remark!";
        $gradefilling->score = 90;
        $gradefilling = $DB->insert_record('gradingform_guide_fillings', $gradefilling);

        $data = data::populate_user_info($student, $cm->id, $course->id);
        $this->assertNotEmpty($data);
    }
}
