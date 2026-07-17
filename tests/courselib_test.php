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
 * Tests for courselib.php helper functions.
 *
 * @package    local_datacurso_ratings
 * @category   test
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_datacurso_ratings_is_enabled_for_course
 * @covers \local_datacurso_ratings_set_course_enabled
 * @covers \local_datacurso_ratings_get_course_enabled
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/datacurso_ratings/courselib.php');

/**
 * Unit tests for course-level plugin configuration helpers.
 */
class courselib_test extends \advanced_testcase {

    /**
     * Verify that the plugin is considered enabled when the global config is active
     * and no course-level record exists.
     *
     * @spec MDL-INT-001 step 1
     */
    public function test_plugin_is_enabled_when_global_on_and_no_course_record(): void {
        $this->resetAfterTest(true);

        set_config('enabled', 1, 'local_datacurso_ratings');
        $course = $this->getDataGenerator()->create_course();

        $this->assertTrue(local_datacurso_ratings_is_enabled_for_course($course->id));
    }

    /**
     * Verify that a disabled global config overrides any course-level config,
     * resulting in the plugin being disabled regardless of course settings.
     *
     * @spec MDL-INT-001 step 2
     */
    public function test_global_disabled_prevails_over_course_config(): void {
        $this->resetAfterTest(true);

        set_config('enabled', 0, 'local_datacurso_ratings');
        $course = $this->getDataGenerator()->create_course();

        // Even if we insert a course-level enabled record, global off must win.
        local_datacurso_ratings_set_course_enabled($course->id, true);

        $this->assertFalse(local_datacurso_ratings_is_enabled_for_course($course->id));
    }

    /**
     * Verify that a course-level record with enabled=false disables the plugin
     * for that specific course even when the global config is active.
     *
     * @spec MDL-INT-001 step 3
     */
    public function test_course_record_disabled_disables_plugin_for_that_course(): void {
        $this->resetAfterTest(true);

        set_config('enabled', 1, 'local_datacurso_ratings');
        $course = $this->getDataGenerator()->create_course();

        local_datacurso_ratings_set_course_enabled($course->id, false);

        $this->assertFalse(local_datacurso_ratings_is_enabled_for_course($course->id));
    }

    /**
     * Verify that inserting a new course configuration and subsequently updating it
     * produces the expected persisted value in both cases.
     *
     * @spec MDL-INT-001 step 4
     */
    public function test_insert_and_update_course_config(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();

        // Insert (no prior record).
        local_datacurso_ratings_set_course_enabled($course->id, true);
        $this->assertTrue(local_datacurso_ratings_get_course_enabled($course->id));

        $count = $DB->count_records('local_datacurso_ratings_course_settings', ['courseid' => $course->id]);
        $this->assertEquals(1, $count, 'Only one record should exist after insert.');

        // Update (record already exists).
        local_datacurso_ratings_set_course_enabled($course->id, false);
        $this->assertFalse(local_datacurso_ratings_get_course_enabled($course->id));

        $count = $DB->count_records('local_datacurso_ratings_course_settings', ['courseid' => $course->id]);
        $this->assertEquals(1, $count, 'Update must not duplicate the record.');
    }

    /**
     * Verify that querying configuration for a course with no record returns null,
     * not false or any other falsy value.
     *
     * @spec MDL-INT-001 step 5
     */
    public function test_get_course_enabled_returns_null_when_no_record(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();

        $result = local_datacurso_ratings_get_course_enabled($course->id);

        $this->assertNull($result, 'Expected null when no course-level config record exists.');
    }
}
