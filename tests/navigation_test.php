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
 * Tests for the course navigation extension.
 *
 * @package    local_datacurso_ratings
 * @category   test
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers ::local_datacurso_ratings_extend_navigation_course
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/datacurso_ratings/lib.php');

/**
 * Tests for the course navigation report link (INT-016).
 */
class navigation_test extends \advanced_testcase {

    /**
     * Build a minimal navigation node tree that simulates the course navigation
     * with a "coursereports" child node.
     *
     * @return navigation_node
     */
    private function build_navigation_with_reports(): navigation_node {
        $root = navigation_node::create('Root', null, navigation_node::TYPE_ROOTNODE, null, 'root');

        $reportsnode = navigation_node::create(
            'Reports',
            null,
            navigation_node::TYPE_CONTAINER,
            null,
            'coursereports'
        );
        $root->add_node($reportsnode);

        return $root;
    }

    /**
     * Verify that the report link is added to course navigation for users with the
     * viewcoursereport capability.
     *
     * @spec MDL-INT-016 step 1
     */
    public function test_report_link_appears_for_user_with_capability(): void {
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        // Create a teacher — teachers have viewcoursereport by default (see db/access.php).
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        $this->setUser($teacher);

        $navigation = $this->build_navigation_with_reports();

        local_datacurso_ratings_extend_navigation_course($navigation, $course, $context);

        $reportsnode = $navigation->get('coursereports');
        $this->assertNotFalse($reportsnode, 'The coursereports node must exist.');

        $reportlink = $reportsnode->get('local_datacurso_ratings_report');
        $this->assertNotFalse(
            $reportlink,
            'The report link must be added for a user with the viewcoursereport capability.'
        );
    }

    /**
     * Verify that the report link is NOT added to course navigation for users without
     * the viewcoursereport capability.
     *
     * @spec MDL-INT-016 step 2
     */
    public function test_report_link_absent_for_user_without_capability(): void {
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        // A plain student has no viewcoursereport capability by default.
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        $this->setUser($student);

        $navigation = $this->build_navigation_with_reports();

        local_datacurso_ratings_extend_navigation_course($navigation, $course, $context);

        $reportsnode = $navigation->get('coursereports');
        $this->assertNotFalse($reportsnode, 'The coursereports node must exist.');

        $reportlink = $reportsnode->get('local_datacurso_ratings_report');
        $this->assertFalse(
            $reportlink,
            'The report link must NOT be added for a user without the viewcoursereport capability.'
        );
    }
}
