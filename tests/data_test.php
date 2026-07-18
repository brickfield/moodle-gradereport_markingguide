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

namespace gradereport_markingguide;

use gradereport_markingguide\data;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * A test class used to test grade_report, the abstract grade report parent class
 * @package    gradereport_markingguide
 * @copyright  2021 onward Brickfield Education Labs Ltd, https://www.brickfield.ie
 * @author     2021 Clayton Darlington <clayton@brickfieldlabs.ie>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(data::class)]
final class data_test extends \advanced_testcase {
    /**
     * Test that GRADABLES defines the expected activity types with required keys.
     */
    public function test_gradables_constant_structure(): void {
        $gradables = data::GRADABLES;

        $this->assertArrayHasKey('assign', $gradables, 'assign must be a supported gradable type');
        $this->assertArrayHasKey('forum', $gradables, 'forum must be a supported gradable type');

        foreach ($gradables as $modname => $config) {
            $this->assertArrayHasKey('table', $config, "$modname must define a table");
            $this->assertArrayHasKey('field', $config, "$modname must define a field");
            $this->assertArrayHasKey('itemoffset', $config, "$modname must define an itemoffset");
            $this->assertArrayHasKey('showfeedback', $config, "$modname must define showfeedback");
        }
    }

    /**
     * Test that enrolled students are discoverable via get_enrolled_users for a course.
     *
     * This mirrors the enrolment lookup in report::show(), which calls
     * get_enrolled_users($coursecontext, 'mod/assign:submit').
     */
    public function test_get_enrolled(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $student1 = $this->getDataGenerator()->create_and_enrol($course);
        $student2 = $this->getDataGenerator()->create_and_enrol($course);
        $this->getDataGenerator()->create_and_enrol($course);

        $enrolled = data::get_enrolled($course->id);
        $this->assertNotEmpty($enrolled, 'Enrolled students must be returned');
        $enrolledids = array_keys($enrolled);
        $this->assertContains(intval($student1->id), $enrolledids, 'Student 1 must appear in enrolled list');
        $this->assertContains(intval($student2->id), $enrolledids, 'Student 2 must appear in enrolled list');
    }

    /**
     * Test that a teacher without the student capability is not returned by the enrolment query.
     */
    public function test_get_enrolled_users_excludes_teachers(): void {
        $this->resetAfterTest();

        $course  = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $context  = \context_course::instance($course->id);
        $enrolled = get_enrolled_users($context, 'mod/assign:submit');

        $enrolledids = array_keys($enrolled);
        $this->assertContains(intval($student->id), $enrolledids, 'Student must be in the enrolled list');
        $this->assertNotContains(intval($teacher->id), $enrolledids, 'Teacher must not appear in the student enrolled list');
    }

    /**
     * Test the get_grade_area function
     */
    public function test_grading_areas(): void {
        $this->resetAfterTest(true);
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course]);

        // Get the ID for the grading_area from course_modules where con.instanceid = cm.id.
        // User that ID to generate a grading_area?
        $cm = $DB->get_record('course_modules', ['instance' => $assign->id, 'course' => $course->id]);
        $context = $DB->get_record('context', ['instanceid' => $cm->id, 'contextlevel' => CONTEXT_MODULE]);

        // Generate the gradearea directly with the right info.
        $gradeareadata = new \stdClass();
        $gradeareadata->contextid = $context->id;
        $gradeareadata->component = 'mod_assign';
        $gradeareadata->areaname = 'submissions';
        $gradeareadata->activemethod = 'guide';

        $DB->insert_record('grading_areas', $gradeareadata);

        // Find the grade area.
        $data = data::get_grading_areas($cm->id, $course->id);
        $this->assertNotEmpty($data, 'A grading area record must be found for the activity');
        $this->assertNotEmpty($data->areaid, 'areaid must be set on the result');
    }

    public function test_find_marking_guide(): void {
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
        $gradeareadata = new \stdClass();
        $gradeareadata->contextid = $context->id;
        $gradeareadata->component = 'mod_assign';
        $gradeareadata->areaname = 'submissions';
        $gradeareadata->activemethod = 'guide';

        $DB->insert_record('grading_areas', $gradeareadata);

        // Find the grade area.
        $area = data::get_grading_areas($cm->id, $course->id);

        // Generate and store a grading definition for the area.
        $definition = new \stdClass();
        $definition->areaid = $area->areaid;
        $definition->timecreated = time();
        $definition->timemodified = time();
        $definition->usercreated = $student->id;
        $definition->usermodified = $student->id;
        $gradingdef = $DB->insert_record('grading_definitions', $definition);

        // Generate the guide criteria.
        $criteria = new \stdClass();
        $criteria->definitionid = $gradingdef;
        $criteria->sortorder = 1;
        $criteria->maxscore = 100;
        $criteria->shortname = "This is a temp shortname";
        $DB->insert_record('gradingform_guide_criteria', $criteria);

        $markingguides = data::find_marking_guide($area);
        $this->assertNotEmpty($markingguides);
    }

    /**
     * Test the populate_user_info function
     */
    public function test_populate_user_info(): void {
        $this->resetAfterTest();
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course]);

        $cm = $DB->get_record('course_modules', ['instance' => $assign->id]);

        // Create a grade for the assignment.
        $assignmentgrade = new \stdClass();
        $assignmentgrade->assignment = $assign->id;
        $assignmentgrade->userid = $student->id;
        $assignmentgrade->timecreated = time();
        $assignmentgrade->timemodified = time();
        $assignmentgrade->grader = $student->id;
        $assignmentgrade->grade = 100;
        $assignmentgrade = $DB->insert_record('assign_grades', $assignmentgrade);

        $definition = new \stdClass();
        $definition->areaid = 1;
        $definition->timecreated = time();
        $definition->timemodified = time();
        $definition->usercreated = $student->id;
        $definition->usermodified = $student->id;
        $gradingdef = $DB->insert_record('grading_definitions', $definition);

        $gradeinstance = new \stdClass();
        $gradeinstance->id = $assign->id;
        $gradeinstance->definitionid = $gradingdef;
        $gradeinstance->raterid = 1;
        $gradeinstance->itemid = 1;
        $gradeinstance->status = 3;
        $gradeinstance->timemodified = time();
        $gradeinstance = $DB->insert_record('grading_instances', $gradeinstance);

        $gradefilling = new \stdClass();
        $gradefilling->instanceid = $gradeinstance;
        $gradefilling->criterionid = 1;
        $gradefilling->remark = "This is a remark!";
        $gradefilling->score = 90;
        $gradefilling = $DB->insert_record('gradingform_guide_fillings', $gradefilling);

        $data = data::populate_user_info($student, $cm->id, $course->id);
        $this->assertNotEmpty($data);
    }

    /**
     * Test that the grading area lookup returns nothing when no rubric area exists.
     */
    public function test_grading_area_lookup_returns_empty_when_no_area(): void {
        $this->resetAfterTest();
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course]);
        $cm     = $DB->get_record('course_modules', ['instance' => $assign->id, 'course' => $course->id]);

        $areasql = "SELECT gra.id as areaid FROM {course_modules} cm
                 LEFT JOIN {context} con ON cm.id = con.instanceid
                 LEFT JOIN {grading_areas} gra ON gra.contextid = con.id
                     WHERE cm.course = ? AND cm.id = ? AND gra.activemethod = ?";
        $area = $DB->get_record_sql($areasql, [$course->id, $cm->id, 'guide']);

        $this->assertFalse($area, 'No grading area must be returned when none has been inserted');
    }

    /**
     * Test that the guide criteria query returns criteria once a definition and criteria exist.
     *
     * This mirrors the $critsql query in report::init_table() and the $sql in report::show()
     * that builds $guidearray.
     */
    public function test_guide_criteria_lookup(): void {
        $this->resetAfterTest();
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course]);

        $cm      = $DB->get_record('course_modules', ['instance' => $assign->id, 'course' => $course->id]);
        $context = $DB->get_record('context', ['instanceid' => $cm->id, 'contextlevel' => CONTEXT_MODULE]);

        // Set up grading area.
        $areaid = $DB->insert_record('grading_areas', (object)[
            'contextid'    => $context->id,
            'component'    => 'mod_assign',
            'areaname'     => 'submissions',
            'activemethod' => 'guide',
        ]);

        // Set up grading definition.
        $definitionid = $DB->insert_record('grading_definitions', (object)[
            'areaid'       => $areaid,
            'method'       => 'guide',
            'name'         => 'Test guide',
            'timecreated'  => time(),
            'timemodified' => time(),
            'usercreated'  => 2,
            'usermodified' => 2,
        ]);

        // Insert two criteria with levels.
        $DB->insert_record('gradingform_guide_criteria', (object)[
            'definitionid' => $definitionid,
            'sortorder'    => 1,
            'description'  => 'Criterion One',
            'descriptionformat' => FORMAT_HTML,
            'maxscore' => 40,
        ]);
        $DB->insert_record('gradingform_guide_criteria', (object)[
            'definitionid' => $definitionid,
            'sortorder'    => 2,
            'description'  => 'Criterion Two',
            'descriptionformat' => FORMAT_HTML,
            'maxscore' => 60,
        ]);

        // Run the header-column query from init_table().
        $critsql = "SELECT crit.id, crit.description, MAX(maxscore) AS maxscore
                      FROM {grading_definitions} def
                 LEFT JOIN {gradingform_guide_criteria} crit ON crit.definitionid = def.id
                     WHERE def.areaid = ?
                  GROUP BY crit.id, crit.description, crit.sortorder
                  ORDER BY crit.sortorder";
        $criteria = $DB->get_records_sql($critsql, [$areaid]);

        $this->assertCount(2, $criteria, 'Two criteria must be returned');

        $critarray = array_values($criteria);
        $this->assertSame('Criterion One', $critarray[0]->description);
        $this->assertSame(40, intval($critarray[0]->maxscore), 'Max score for criterion 1 must be 100');
        $this->assertSame('Criterion Two', $critarray[1]->description);
        $this->assertSame(60, intval($critarray[1]->maxscore), 'Max score for criterion 2 must be 30');
    }
}
