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
 * Backup and restore integration tests for local_datacurso_ratings.
 *
 * These tests are SKIPPED because the backup/restore feature is pending
 * implementation.  The test bodies document the EXPECTED behavior so that
 * they can be activated once the feature is built.
 *
 * @package    local_datacurso_ratings
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Test suite for course backup/restore cycle preserving plugin data.
 *
 * Status: PENDING IMPLEMENTATION
 * All tests are skipped until backup/restore support is added to the plugin.
 * See MDL-INT-022 in the test specification.
 */
class backup_restore_test extends \advanced_testcase {

    /**
     * Skip guard: all tests in this class are skipped until the feature ships.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->markTestSkipped('Backup/restore pending implementation.');
    }

    // -------------------------------------------------------------------------
    // The test stubs below document the expected behavior (MDL-INT-022).
    // Remove the setUp() skip guard and fill in the bodies once the feature
    // is implemented.
    // -------------------------------------------------------------------------

    /**
     * Student ratings are present in the restored course after a full backup/restore cycle.
     *
     * Expected steps once implemented:
     *  1. Create a course with at least one module and two student users.
     *  2. Each student rates the module (like / dislike).
     *  3. Run backup_controller for the course (MODE_GENERAL, TYPE_COURSE).
     *  4. Run restore_controller into a new course.
     *  5. Assert that local_datacurso_ratings rows exist in the restored course
     *     with the original userid and rating values mapped to the new cmid.
     *
     * MDL-INT-022 step 1
     */
    public function test_student_ratings_survive_backup_restore_cycle(): void {
        // This body is intentionally empty — the setUp() skip guard fires first.
        // Implement after the backup/restore feature lands.
    }

    /**
     * Per-course plugin configuration is preserved after a full backup/restore cycle.
     *
     * Expected steps once implemented:
     *  1. Create a course and set local_datacurso_ratings config for it
     *     (e.g. enabled = false via the course settings).
     *  2. Run backup_controller then restore_controller into a new course.
     *  3. Assert that the plugin config row exists for the restored course with
     *     the same settings as the source course.
     *
     * MDL-INT-022 step 2
     */
    public function test_per_course_plugin_config_survives_backup_restore_cycle(): void {
        // This body is intentionally empty — the setUp() skip guard fires first.
        // Implement after the backup/restore feature lands.
    }
}
