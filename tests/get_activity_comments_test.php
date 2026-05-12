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

namespace local_datacurso_ratings\external;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the get_activity_comments external function.
 *
 * @package    local_datacurso_ratings
 * @copyright  2025 Industria Elearning <info@industriaelearning.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_datacurso_ratings\external\get_activity_comments
 */
class get_activity_comments_test extends \advanced_testcase {

    /**
     * Test that the date/timestamp returned for a comment reflects the last
     * modification time (timemodified), NOT the creation time (timecreated).
     *
     * Regression test: previously the query only selected timecreated and used
     * it for both the formatted date and the raw timestamp, so edits to a rating
     * were never reflected in the modal table.
     */
    public function test_comment_date_uses_timemodified_not_timecreated(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Set up course and activity module.
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        // Create a student and enrol them.
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        // Use clearly distinct timestamps so the wrong field is immediately obvious.
        $timecreated  = mktime(10, 0, 0, 1, 1, 2024);  // 2024-01-01 10:00:00
        $timemodified = mktime(15, 30, 0, 6, 15, 2024); // 2024-06-15 15:30:00

        $DB->insert_record('local_datacurso_ratings', [
            'cmid'         => $page->cmid,
            'courseid'     => $course->id,
            'categoryid'   => $course->category,
            'userid'       => $user->id,
            'rating'       => 1,
            'feedback'     => 'Great activity!',
            'timecreated'  => $timecreated,
            'timemodified' => $timemodified,
        ]);

        $result = get_activity_comments::execute($page->cmid);

        $this->assertCount(1, $result['comments']);
        $comment = $result['comments'][0];

        $this->assertSame(
            $timemodified,
            $comment['timestamp'],
            'The timestamp must reflect the last modification time (timemodified), not the creation time (timecreated).'
        );

        $this->assertNotSame(
            $timecreated,
            $comment['timestamp'],
            'The timestamp must NOT be the original creation time (timecreated).'
        );
    }

    /**
     * Test that the comment list is ordered by timemodified descending, so the
     * most recently edited rating appears first — regardless of when it was
     * originally created.
     */
    public function test_comments_ordered_by_timemodified_descending(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $page   = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user1->id, $course->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course->id);

        $now = time();

        // User 1: created first (oldest), but modified most recently.
        $DB->insert_record('local_datacurso_ratings', [
            'cmid'         => $page->cmid,
            'courseid'     => $course->id,
            'categoryid'   => $course->category,
            'userid'       => $user1->id,
            'rating'       => 1,
            'feedback'     => 'First created, last modified',
            'timecreated'  => $now - 200,
            'timemodified' => $now - 10,
        ]);

        // User 2: created later, but modified earlier.
        $DB->insert_record('local_datacurso_ratings', [
            'cmid'         => $page->cmid,
            'courseid'     => $course->id,
            'categoryid'   => $course->category,
            'userid'       => $user2->id,
            'rating'       => 0,
            'feedback'     => 'Second created, first modified',
            'timecreated'  => $now - 100,
            'timemodified' => $now - 50,
        ]);

        $result = get_activity_comments::execute($page->cmid);

        $this->assertCount(2, $result['comments']);

        // User 1's comment (timemodified = $now - 10) must come first.
        $this->assertSame(
            $now - 10,
            $result['comments'][0]['timestamp'],
            'The most recently modified comment must appear first.'
        );

        $this->assertSame(
            $now - 50,
            $result['comments'][1]['timestamp'],
            'The earlier modified comment must appear second.'
        );
    }
}
