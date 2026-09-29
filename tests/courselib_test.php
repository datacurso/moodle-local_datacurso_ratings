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
 */

namespace local_datacurso_ratings;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/datacurso_ratings/courselib.php');

/**
 * Unit tests for course-level plugin configuration helpers.
 *
 * @covers ::local_datacurso_ratings_is_enabled_for_course
 * @covers ::local_datacurso_ratings_set_course_enabled
 * @covers ::local_datacurso_ratings_get_course_enabled
 * @covers ::local_datacurso_ratings_get_supported_modules
 * @covers ::local_datacurso_ratings_is_module_supported
 */
final class courselib_test extends \advanced_testcase {
    /**
     * Verify that the plugin is considered enabled when the global config is active
     * and no course-level record exists.
     *
     * Spec: MDL-INT-001 step 1.
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
     * Spec: MDL-INT-001 step 2.
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
     * Spec: MDL-INT-001 step 3.
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
     * Spec: MDL-INT-001 step 4.
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
     * Spec: MDL-INT-001 step 5.
     */
    public function test_get_course_enabled_returns_null_when_no_record(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();

        $result = local_datacurso_ratings_get_course_enabled($course->id);

        $this->assertNull($result, 'Expected null when no course-level config record exists.');
    }

    /**
     * Verify that the supported module list contains the core activity types the widget
     * renders on and excludes structural modules such as labels.
     */
    public function test_supported_modules_lists_core_activity_types(): void {
        $modules = local_datacurso_ratings_get_supported_modules();

        $this->assertContains('quiz', $modules);
        $this->assertContains('page', $modules);
        $this->assertContains('forum', $modules);
        $this->assertNotContains('label', $modules);
        $this->assertNotContains('subsection', $modules);
        $this->assertSame($modules, array_values(array_unique($modules)), 'Module list must have no duplicates.');
    }

    /**
     * Verify the module support predicate for supported, unsupported and empty names.
     */
    public function test_is_module_supported_matches_supported_list(): void {
        $this->assertTrue(local_datacurso_ratings_is_module_supported('quiz'));
        $this->assertTrue(local_datacurso_ratings_is_module_supported('h5pactivity'));
        $this->assertFalse(local_datacurso_ratings_is_module_supported('label'));
        $this->assertFalse(local_datacurso_ratings_is_module_supported(''));
    }

    /**
     * Stored setting values and the effective comment length they resolve to.
     *
     * @return array[]
     */
    public static function max_comment_length_provider(): array {
        return [
            'unset uses default'       => ['configured' => null, 'expected' => 200],
            'zero uses default'        => ['configured' => 0, 'expected' => 200],
            'negative uses default'    => ['configured' => -5, 'expected' => 200],
            'lower bound is kept'      => ['configured' => 1, 'expected' => 1],
            'in range is kept'         => ['configured' => 350, 'expected' => 350],
            'upper bound is kept'      => ['configured' => 2000, 'expected' => 2000],
            'above upper bound capped' => ['configured' => 999999, 'expected' => 2000],
        ];
    }

    /**
     * Verify that the effective comment length is always within 1..2000.
     *
     * @dataProvider max_comment_length_provider
     * @param int|null $configured Value to store in the setting (null leaves it unset).
     * @param int $expected Effective limit.
     */
    public function test_get_max_comment_length_is_bounded(?int $configured, int $expected): void {
        $this->resetAfterTest(true);

        if ($configured !== null) {
            set_config('maxcommentlength', $configured, 'local_datacurso_ratings');
        } else {
            unset_config('maxcommentlength', 'local_datacurso_ratings');
        }

        $this->assertSame($expected, local_datacurso_ratings_get_max_comment_length());
    }
}
