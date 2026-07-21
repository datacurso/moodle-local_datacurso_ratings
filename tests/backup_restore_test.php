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
 * @package    local_datacurso_ratings
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_datacurso_ratings;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/local/datacurso_ratings/courselib.php');
require_once($CFG->dirroot . '/local/datacurso_ratings/backup/moodle2/restore_local_datacurso_ratings_plugin.class.php');

/**
 * Test suite for course backup/restore cycle preserving plugin data.
 *
 * See MDL-INT-022 in the test specification.
 *
 * @covers \restore_local_datacurso_ratings_plugin
 */
final class backup_restore_test extends \advanced_testcase {
    /**
     * Backs up the given course (including user info) and returns the backup id.
     *
     * @param \stdClass $srccourse Course object to back up.
     * @return string Backup id of the executed backup.
     */
    protected function backup_course(\stdClass $srccourse): string {
        global $CFG, $USER;

        // Turn off file logging, otherwise it can't delete the file (Windows).
        $CFG->backup_file_logger_level = \backup::LOG_NONE;

        // Do backup with default settings. MODE_IMPORT means it will just
        // create the directory and not zip it.
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $srccourse->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id
        );

        // Include user info so per-user ratings travel with the backup.
        $bc->get_plan()->get_setting('users')->set_status(\backup_setting::NOT_LOCKED);
        $bc->get_plan()->get_setting('users')->set_value(true);

        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        return $backupid;
    }

    /**
     * Backs up the given course (including user info) and restores it into a new course.
     *
     * @param \stdClass $srccourse Course object to back up.
     * @return int ID of the newly restored course.
     */
    protected function backup_and_restore(\stdClass $srccourse): int {
        global $USER;

        $backupid = $this->backup_course($srccourse);

        // Do restore to a new course with user info enabled.
        $newcourseid = \restore_dbops::create_new_course(
            $srccourse->fullname,
            $srccourse->shortname . '_copy',
            $srccourse->category
        );
        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );

        $rc->get_plan()->get_setting('users')->set_status(\backup_setting::NOT_LOCKED);
        $rc->get_plan()->get_setting('users')->set_value(true);

        $this->assertTrue($rc->execute_precheck());

        // Buffer stray output from third-party restore plugins so the test is not flagged risky.
        ob_start();
        try {
            $rc->execute_plan();
        } finally {
            ob_end_clean();
        }
        $rc->destroy();

        return $newcourseid;
    }

    /**
     * Backs up the given course and restores it into an ALREADY EXISTING course.
     *
     * Uses backup::TARGET_EXISTING_ADDING with course configuration overwrite
     * enabled, so course-level plugin data (course.xml) is processed against a
     * course that already holds plugin rows from a previous restore.
     *
     * @param \stdClass $srccourse Course object to back up.
     * @param int $targetcourseid ID of the existing course to restore into.
     * @return void
     */
    protected function backup_and_restore_into_existing(\stdClass $srccourse, int $targetcourseid): void {
        global $USER;

        $backupid = $this->backup_course($srccourse);

        $rc = new \restore_controller(
            $backupid,
            $targetcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_EXISTING_ADDING
        );

        $rc->get_plan()->get_setting('users')->set_status(\backup_setting::NOT_LOCKED);
        $rc->get_plan()->get_setting('users')->set_value(true);

        // The course_info structure step (which dispatches course-level plugin
        // paths) only runs into an existing course when overwrite_conf is on.
        $rc->get_plan()->get_setting('overwrite_conf')->set_status(\backup_setting::NOT_LOCKED);
        $rc->get_plan()->get_setting('overwrite_conf')->set_value(true);

        $this->assertTrue($rc->execute_precheck());

        // Buffer stray output from third-party restore plugins so the test is not flagged risky.
        ob_start();
        try {
            $rc->execute_plan();
        } finally {
            ob_end_clean();
        }
        $rc->destroy();
    }

    /**
     * Builds a restore plugin instance wired to drive the rating processor directly.
     *
     * Through the normal restore controller flow the rating update branch is
     * unreachable (restores always create fresh cmids), so this helper bypasses
     * the plan machinery: it injects a minimal task stub returning fixed
     * course/module ids and overrides get_mappingid() with an identity mapping.
     *
     * @param int $courseid Real course id the processor should target.
     * @param int $cmid Real course module id the processor should target.
     * @return \restore_local_datacurso_ratings_plugin
     */
    protected function create_rating_processor(int $courseid, int $cmid): \restore_local_datacurso_ratings_plugin {
        $task = new class ($courseid, $cmid) {
            /** @var int Course id returned to the processor. */
            private $courseid;

            /** @var int Course module id returned to the processor. */
            private $cmid;

            /**
             * Constructor.
             *
             * @param int $courseid Course id to expose.
             * @param int $cmid Course module id to expose.
             */
            public function __construct(int $courseid, int $cmid) {
                $this->courseid = $courseid;
                $this->cmid = $cmid;
            }

            /**
             * Mimics restore_task::get_courseid().
             *
             * @return int
             */
            public function get_courseid(): int {
                return $this->courseid;
            }

            /**
             * Mimics restore_activity_task::get_moduleid().
             *
             * @return int
             */
            public function get_moduleid(): int {
                return $this->cmid;
            }
        };

        return new class ($task) extends \restore_local_datacurso_ratings_plugin {
            /**
             * Bypasses the parent constructor: there is no restore step here,
             * the processor is exercised directly against injected task data.
             *
             * @param object $task Minimal task stub with get_courseid()/get_moduleid().
             */
            public function __construct($task) {
                $this->plugintype = 'local';
                $this->pluginname = 'datacurso_ratings';
                $this->task = $task;
            }

            /**
             * Identity mapping: the user is always considered part of the restore.
             *
             * @param string $itemname the type of item
             * @param int $oldid the item ID from the backup
             * @param mixed $ifnotfound what to return if $oldid wasn't found
             * @return int
             */
            protected function get_mappingid($itemname, $oldid, $ifnotfound = false) {
                return $oldid;
            }
        };
    }

    /**
     * Student ratings are present in the restored course after a full backup/restore cycle.
     *
     * MDL-INT-022 step 1
     */
    public function test_student_ratings_survive_backup_restore_cycle(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $page = $generator->create_module('page', ['course' => $course->id]);
        $student1 = $generator->create_and_enrol($course, 'student');
        $student2 = $generator->create_and_enrol($course, 'student');

        $now = time();
        $seed = [
            [$student1->id, 1, 'Great activity'],
            [$student2->id, 0, 'Not useful'],
        ];
        foreach ($seed as [$userid, $rating, $feedback]) {
            $DB->insert_record('local_datacurso_ratings', (object) [
                'cmid' => $page->cmid,
                'courseid' => $course->id,
                'categoryid' => $course->category,
                'userid' => $userid,
                'rating' => $rating,
                'feedback' => $feedback,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }

        // Global feedback phrases must NOT be duplicated by a course backup/restore cycle.
        $DB->insert_record('local_datacurso_ratings_feedback', (object) [
            'feedbacktext' => 'Global preset phrase',
            'type' => 'like',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $globalfeedbackcount = $DB->count_records('local_datacurso_ratings_feedback');

        $newcourseid = $this->backup_and_restore($course);

        // Resolve the restored (remapped) course module id.
        $modinfo = get_fast_modinfo($newcourseid);
        $instances = $modinfo->get_instances_of('page');
        $this->assertCount(1, $instances);
        $newcmid = reset($instances)->id;
        $this->assertNotEquals($page->cmid, $newcmid);

        // Both ratings must exist in the restored course, remapped to the new cmid.
        $restored = $DB->get_records('local_datacurso_ratings', ['courseid' => $newcourseid]);
        $this->assertCount(2, $restored);

        $byuser = [];
        foreach ($restored as $record) {
            $byuser[$record->userid] = $record;
        }

        $newcourse = $DB->get_record('course', ['id' => $newcourseid], '*', MUST_EXIST);

        $this->assertArrayHasKey($student1->id, $byuser);
        $this->assertEquals(1, $byuser[$student1->id]->rating);
        $this->assertEquals('Great activity', $byuser[$student1->id]->feedback);
        $this->assertEquals($newcmid, $byuser[$student1->id]->cmid);
        $this->assertEquals($newcourse->category, $byuser[$student1->id]->categoryid);

        $this->assertArrayHasKey($student2->id, $byuser);
        $this->assertEquals(0, $byuser[$student2->id]->rating);
        $this->assertEquals('Not useful', $byuser[$student2->id]->feedback);
        $this->assertEquals($newcmid, $byuser[$student2->id]->cmid);

        // The original course ratings are untouched.
        $this->assertEquals(2, $DB->count_records('local_datacurso_ratings', ['courseid' => $course->id]));

        // Negative: the global feedback table is NOT part of the course backup.
        $this->assertEquals($globalfeedbackcount, $DB->count_records('local_datacurso_ratings_feedback'));
    }

    /**
     * Per-course plugin configuration is preserved after a full backup/restore cycle.
     *
     * MDL-INT-022 step 2
     */
    public function test_per_course_plugin_config_survives_backup_restore_cycle(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        // Disable the plugin for the source course (non-default value).
        local_datacurso_ratings_set_course_enabled($course->id, false);

        $newcourseid = $this->backup_and_restore($course);

        // The restored course carries the same per-course configuration.
        $record = $DB->get_record('local_datacurso_ratings_course_settings', ['courseid' => $newcourseid]);
        $this->assertNotFalse($record, 'Course settings row missing for the restored course.');
        $this->assertEquals(0, $record->enabled);

        // Exactly one row was inserted for the brand-new course (this cycle only
        // exercises the insert path; the update path is covered separately below).
        $this->assertEquals(1, $DB->count_records(
            'local_datacurso_ratings_course_settings',
            ['courseid' => $newcourseid]
        ));

        // The source course settings are untouched.
        $srcrecord = $DB->get_record('local_datacurso_ratings_course_settings', ['courseid' => $course->id]);
        $this->assertNotFalse($srcrecord);
        $this->assertEquals(0, $srcrecord->enabled);
    }

    /**
     * A second restore into an already-restored course updates the existing
     * settings row instead of inserting a duplicate (update branch of the upsert).
     *
     * MDL-INT-022 step 2 (update path)
     */
    public function test_per_course_plugin_config_second_restore_updates_existing_row(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        // First cycle: restore into a brand-new course, which INSERTS the row.
        local_datacurso_ratings_set_course_enabled($course->id, false);
        $newcourseid = $this->backup_and_restore($course);
        $this->assertEquals(0, $DB->get_field(
            'local_datacurso_ratings_course_settings',
            'enabled',
            ['courseid' => $newcourseid],
            MUST_EXIST
        ));

        // Change the source value, then restore AGAIN into the already-restored
        // course: the settings row already exists, so the update branch must run.
        local_datacurso_ratings_set_course_enabled($course->id, true);
        $this->backup_and_restore_into_existing($course, $newcourseid);

        // Still exactly one row for the course: the unique courseid index was
        // respected because the existing row was updated, not re-inserted.
        $records = $DB->get_records('local_datacurso_ratings_course_settings', ['courseid' => $newcourseid]);
        $this->assertCount(1, $records);

        // The row carries the value from the second backup, proving the update ran.
        $this->assertEquals(1, reset($records)->enabled);
    }

    /**
     * Processing the same rating twice updates the existing row for the
     * (cmid, userid) pair instead of violating the unique index.
     *
     * The restore controller flow can never reach this branch (restores always
     * create fresh cmids), so the processor is driven directly.
     */
    public function test_rating_restore_updates_existing_row_for_same_cmid_and_user(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $page = $generator->create_module('page', ['course' => $course->id]);
        $student = $generator->create_and_enrol($course, 'student');

        $processor = $this->create_rating_processor($course->id, $page->cmid);

        $now = time();
        $first = [
            'userid' => $student->id,
            'rating' => 1,
            'feedback' => 'First impression',
            'timecreated' => $now - 100,
            'timemodified' => $now - 100,
        ];
        $processor->process_datacurso_ratings_rating($first);

        // Insert path: exactly one row exists for the (cmid, userid) pair.
        $this->assertEquals(1, $DB->count_records(
            'local_datacurso_ratings',
            ['cmid' => $page->cmid, 'userid' => $student->id]
        ));

        // Second payload for the SAME (cmid, userid): the update branch must run,
        // otherwise insert_record would raise dml_write_exception (unique index).
        $second = $first;
        $second['rating'] = 0;
        $second['feedback'] = 'Changed my mind';
        $processor->process_datacurso_ratings_rating($second);

        $rows = $DB->get_records(
            'local_datacurso_ratings',
            ['cmid' => $page->cmid, 'userid' => $student->id]
        );
        $this->assertCount(1, $rows);

        // The single row reflects the second payload, proving the update executed.
        $row = reset($rows);
        $this->assertEquals(0, $row->rating);
        $this->assertEquals('Changed my mind', $row->feedback);
        $this->assertEquals($course->id, $row->courseid);
        $this->assertEquals($course->category, $row->categoryid);
    }
}
