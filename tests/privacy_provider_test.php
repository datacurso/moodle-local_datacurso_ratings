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
 * Tests for the GDPR privacy provider.
 *
 * @package    local_datacurso_ratings
 * @category   test
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_datacurso_ratings\privacy\provider
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for GDPR compliance — export, selective delete, and mass delete (INT-015).
 */
class privacy_provider_test extends \core_privacy\tests\provider_testcase {

    /**
     * Insert a rating record directly for test setup using a real course module.
     *
     * Creates a page module in the given course so that the cmid is a valid
     * course_modules row, satisfying any FK or UNIQUE(cmid, userid) constraints.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $categoryid
     * @param int $rating
     */
    private function insert_rating(int $userid, int $courseid, int $categoryid, int $rating): void {
        global $DB;
        $now  = time();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $courseid]);
        $DB->insert_record('local_datacurso_ratings', (object)[
            'userid'       => $userid,
            'cmid'         => $page->cmid,
            'courseid'     => $courseid,
            'categoryid'   => $categoryid,
            'rating'       => $rating,
            'feedback'     => 'Test feedback',
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Insert a predefined feedback phrase for test setup.
     *
     * @param string $text
     * @param string $type  'like' or 'dislike'
     */
    private function insert_feedback_phrase(string $text, string $type): void {
        global $DB;
        $now = time();
        $DB->insert_record('local_datacurso_ratings_feedback', (object)[
            'feedbacktext' => $text,
            'type'         => $type,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Verify that all ratings for a user are exported correctly (3 ratings = 3 records).
     *
     * @spec MDL-INT-015 step 1
     */
    public function test_export_user_data_returns_all_ratings(): void {
        global $DB;
        $this->resetAfterTest(true);

        $user   = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        // Insert 3 ratings for the user.
        $this->insert_rating($user->id, $course->id, 1, 1);
        $this->insert_rating($user->id, $course->id, 1, 0);
        $this->insert_rating($user->id, $course->id, 2, 1);

        $syscontext = \context_system::instance();

        $contextlist = \local_datacurso_ratings\privacy\provider::get_contexts_for_userid($user->id);
        $this->assertNotEmpty($contextlist->get_contextids(), 'Context list must not be empty for a user with ratings.');

        // Build an approved context list using the system context.
        $approvedids     = new \core_privacy\local\request\approved_contextlist($user, 'local_datacurso_ratings', [(string)$syscontext->id]);
        \local_datacurso_ratings\privacy\provider::export_user_data($approvedids);

        // The writer should have received data for this user.
        $writer = \core_privacy\local\request\writer::with_context($syscontext);
        $data   = $writer->get_data(['Ratings']);

        $this->assertNotNull($data, 'Exported data object must not be null.');
        $this->assertCount(3, $data->entries, 'Exported entries must contain exactly 3 ratings.');
    }

    /**
     * Verify that deleting user A's data does not affect user B's ratings.
     *
     * @spec MDL-INT-015 step 2
     */
    public function test_delete_user_data_does_not_affect_other_users(): void {
        global $DB;
        $this->resetAfterTest(true);

        $usera  = $this->getDataGenerator()->create_user();
        $userb  = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        $this->insert_rating($usera->id, $course->id, 1, 1);
        $this->insert_rating($userb->id, $course->id, 1, 0);

        $syscontext = \context_system::instance();

        $approvedids = new \core_privacy\local\request\approved_contextlist(
            $usera,
            'local_datacurso_ratings',
            [(string)$syscontext->id]
        );
        \local_datacurso_ratings\privacy\provider::delete_data_for_user($approvedids);

        $this->assertFalse(
            $DB->record_exists('local_datacurso_ratings', ['userid' => $usera->id]),
            'User A\'s ratings must be deleted.'
        );
        $this->assertTrue(
            $DB->record_exists('local_datacurso_ratings', ['userid' => $userb->id]),
            'User B\'s ratings must remain untouched after deleting User A.'
        );
    }

    /**
     * Verify that a mass delete in system context removes ratings but preserves feedback phrases.
     *
     * Predefined feedback phrases are site configuration created by the administrator:
     * they hold no personal data (the table has no userid column), so a GDPR mass
     * delete must leave them untouched.
     *
     * @spec MDL-INT-015 step 3
     */
    public function test_mass_delete_in_system_context_preserves_feedback_phrases(): void {
        global $DB;
        $this->resetAfterTest(true);

        $user   = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        $this->insert_rating($user->id, $course->id, 1, 1);
        $this->insert_feedback_phrase('Great activity!', 'like');

        $syscontext = \context_system::instance();
        \local_datacurso_ratings\privacy\provider::delete_data_for_all_users_in_context($syscontext);

        $this->assertEquals(
            0,
            $DB->count_records('local_datacurso_ratings'),
            'All ratings must be deleted after mass delete in system context.'
        );
        $this->assertEquals(
            1,
            $DB->count_records('local_datacurso_ratings_feedback'),
            'Predefined feedback phrases are admin site configuration, not personal data: ' .
            'they must survive a mass delete in system context.'
        );
    }
}
