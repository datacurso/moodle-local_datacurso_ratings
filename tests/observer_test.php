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
 * Tests for the event observers that clean up orphan rating rows.
 *
 * @package    local_datacurso_ratings
 * @category   test
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_datacurso_ratings;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');

/**
 * Deleting a course module or a course must remove the plugin rows that reference them.
 *
 * @covers \local_datacurso_ratings\observer
 */
final class observer_test extends \advanced_testcase {
    /**
     * Insert a rating record for the given user and course module.
     *
     * @param int $userid
     * @param \stdClass $module Module record returned by the generator (has cmid and course).
     */
    private function insert_rating(int $userid, \stdClass $module): void {
        global $DB;
        $now = time();
        $DB->insert_record('local_datacurso_ratings', (object)[
            'userid'       => $userid,
            'cmid'         => $module->cmid,
            'courseid'     => $module->course,
            'categoryid'   => 1,
            'rating'       => 1,
            'feedback'     => 'Test feedback',
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Insert a course settings row for the given course.
     *
     * @param int $courseid
     */
    private function insert_course_settings(int $courseid): void {
        global $DB;
        $now = time();
        $DB->insert_record('local_datacurso_ratings_course_settings', (object)[
            'courseid'     => $courseid,
            'enabled'      => 1,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Deleting a course module removes only the ratings of that module.
     */
    public function test_course_module_deleted_removes_ratings_of_that_module(): void {
        global $DB;
        $this->resetAfterTest(true);

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $page = $generator->create_module('page', ['course' => $course->id]);
        $forum = $generator->create_module('forum', ['course' => $course->id]);
        $student = $generator->create_user();

        $this->insert_rating($student->id, $page);
        $this->insert_rating($student->id, $forum);
        $this->insert_course_settings($course->id);

        course_delete_module($page->cmid);

        $this->assertFalse(
            $DB->record_exists('local_datacurso_ratings', ['cmid' => $page->cmid]),
            'Ratings of a deleted course module must be removed.'
        );
        $this->assertTrue(
            $DB->record_exists('local_datacurso_ratings', ['cmid' => $forum->cmid]),
            'Ratings of other course modules must remain.'
        );
        $this->assertTrue(
            $DB->record_exists('local_datacurso_ratings_course_settings', ['courseid' => $course->id]),
            'Course settings must remain when only a module is deleted.'
        );
    }

    /**
     * Deleting a course removes its ratings and course settings, leaving other courses untouched.
     */
    public function test_course_deleted_removes_ratings_and_course_settings(): void {
        global $DB;
        $this->resetAfterTest(true);

        $generator = $this->getDataGenerator();
        $deleted = $generator->create_course();
        $kept = $generator->create_course();
        $deletedpage = $generator->create_module('page', ['course' => $deleted->id]);
        $keptpage = $generator->create_module('page', ['course' => $kept->id]);
        $student = $generator->create_user();

        $this->insert_rating($student->id, $deletedpage);
        $this->insert_rating($student->id, $keptpage);
        $this->insert_course_settings($deleted->id);
        $this->insert_course_settings($kept->id);

        delete_course($deleted, false);

        $this->assertEquals(0, $DB->count_records('local_datacurso_ratings', ['courseid' => $deleted->id]));
        $this->assertEquals(0, $DB->count_records('local_datacurso_ratings_course_settings', ['courseid' => $deleted->id]));
        $this->assertEquals(1, $DB->count_records('local_datacurso_ratings', ['courseid' => $kept->id]));
        $this->assertEquals(1, $DB->count_records('local_datacurso_ratings_course_settings', ['courseid' => $kept->id]));
    }

    /**
     * The course observer removes rows by course id even when no module event fired for them
     * (for example rows whose cmid no longer exists).
     */
    public function test_course_deleted_removes_orphan_rows_without_module(): void {
        global $DB;
        $this->resetAfterTest(true);

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_user();
        $now = time();

        $DB->insert_record('local_datacurso_ratings', (object)[
            'userid'       => $student->id,
            'cmid'         => 999999,
            'courseid'     => $course->id,
            'categoryid'   => 1,
            'rating'       => 0,
            'feedback'     => 'Orphan row',
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);

        delete_course($course, false);

        $this->assertEquals(0, $DB->count_records('local_datacurso_ratings', ['courseid' => $course->id]));
    }
}
