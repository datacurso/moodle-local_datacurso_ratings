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

namespace local_datacurso_ratings;

use context_user;
use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;

/**
 * Privacy provider tests for local_datacurso_ratings plugin.
 *
 * @package    local_datacurso_ratings
 * @category   test
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class privacy_provider_test extends provider_testcase {
    /**
     * Test that metadata is correctly defined by the provider.
     *
     * @covers ::get_metadata
     * @return void
     */
    public function test_get_metadata(): void {
        $this->resetAfterTest(true);

        $collection = new collection('local_datacurso_ratings');
        $collection = \local_datacurso_ratings\privacy\provider::get_metadata($collection);
        $items = $collection->get_collection();

        // Extract table names from the metadata.
        $foundtables = array_map(fn($item) => $item->get_name(), $items);

        // Savee that the expected tables exist.
        $this->assertContains('local_datacurso_ratings', $foundtables);
        $this->assertContains('local_datacurso_ratings_feedback', $foundtables);
    }

    /**
     * Test that the provider implements required interfaces.
     *
     * @covers ::provider
     * @return void
     */
    public function test_provider_implements_interfaces(): void {
        $provider = new \local_datacurso_ratings\privacy\provider();

        $this->assertInstanceOf(\core_privacy\local\metadata\provider::class, $provider);
        $this->assertInstanceOf(\core_privacy\local\request\plugin\provider::class, $provider);
    }

    /**
     * Test that contexts are correctly returned for a given user.
     *
     * @covers ::get_contexts_for_userid
     * @return void
     */
    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $this->assertEmpty(\local_datacurso_ratings\privacy\provider::get_contexts_for_userid($user->id));

        // Create user data and re-check.
        self::create_userdata($user->id);

        $contextlist = \local_datacurso_ratings\privacy\provider::get_contexts_for_userid($user->id);
        $this->assertNotEmpty($contextlist->get_contextids());
    }

    /**
     * Test that users in context are correctly fetched.
     *
     * @covers ::get_users_in_context
     * @return void
     */
    public function test_get_users_in_context(): void {
        $this->resetAfterTest();
        $component = 'local_datacurso_ratings';

        $user = $this->getDataGenerator()->create_user();
        $usercontext = context_user::instance($user->id);
        $userlist = new userlist($usercontext, $component);

        // Empty initially.
        \local_datacurso_ratings\privacy\provider::get_users_in_context($userlist);
        $this->assertCount(0, $userlist);

        // Create user data. and re-check.
        self::create_userdata($user->id);
        \local_datacurso_ratings\privacy\provider::get_users_in_context($userlist);
        $this->assertNotEmpty($userlist->get_userids());
    }

    /**
     * Test that user data is exported correctly.
     *
     * @covers ::export_user_data
     * @return void
     */
    public function test_export_user_data(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        self::create_userdata($user->id);
        $usercontext = context_user::instance($user->id);

        $writer = writer::with_context($usercontext);
        $this->assertFalse($writer->has_any_data());

        $approvedlist = new approved_contextlist($user, 'local_datacurso_ratings', [$usercontext->id]);
        \local_datacurso_ratings\privacy\provider::export_user_data($approvedlist);

        $data = $writer->get_data(['Ratings']);
        $this->assertNotEmpty($data);
    }

    /**
     * Test deleting all user data for a specific context.
     *
     * @covers ::delete_data_for_all_users_in_context
     * @return void
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;

        $this->resetAfterTest();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        self::create_userdata($user1->id);
        self::create_userdata($user2->id);

        $context = context_system::instance();
        \local_datacurso_ratings\privacy\provider::delete_data_for_all_users_in_context($context);

        $this->assertCount(0, $DB->get_records('local_datacurso_ratings'));
        $this->assertCount(0, $DB->get_records('local_datacurso_ratings_feedback'));
    }

    /**
     * Test deleting data for a single approved user.
     *
     * @covers ::delete_data_for_user
     * @return void
     */
    public function test_delete_data_for_user(): void {
        global $DB;

        $this->resetAfterTest();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        self::create_userdata($user1->id);
        self::create_userdata($user2->id);

        $context = context_system::instance();
        $approvedlist = new approved_contextlist($user1, 'local_datacurso_ratings', [$context->id]);

        \local_datacurso_ratings\privacy\provider::delete_data_for_user($approvedlist);

        $this->assertCount(0, $DB->get_records('local_datacurso_ratings', ['userid' => $user1->id]));
        $this->assertNotEmpty($DB->get_records('local_datacurso_ratings', ['userid' => $user2->id]));
    }

    /**
     * Test deleting data for multiple approved users.
     *
     * @covers ::delete_data_for_users
     * @return void
     */
    public function test_delete_data_for_users(): void {
        global $DB;

        $this->resetAfterTest();
        $component = 'local_datacurso_ratings';
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        self::create_userdata($user1->id);
        self::create_userdata($user2->id);

        $context = context_system::instance();
        $userlist = new userlist($context, $component);
        \local_datacurso_ratings\privacy\provider::get_users_in_context($userlist);
        $this->assertNotEmpty($userlist->get_userids());

        $approvedlist = new approved_userlist($context, $component, [$user1->id]);
        \local_datacurso_ratings\privacy\provider::delete_data_for_users($approvedlist);

        $this->assertCount(0, $DB->get_records('local_datacurso_ratings', ['userid' => $user1->id]));
        $this->assertNotEmpty($DB->get_records('local_datacurso_ratings', ['userid' => $user2->id]));
    }

    /**
     * Helper function to create fake user data for tests.
     *
     * @param int $userid The user ID.
     * @return array The created records.
     */
    private static function create_userdata(int $userid): array {
        global $DB;

        // Simular estructura completa según la tabla real.
        $rating = (object) [
            'userid'       => $userid,
            'cmid'         => 1,
            'courseid'     => 1,
            'categoryid'   => 0,
            'rating'       => 4,
            'feedback'     => 'Test feedback',
            'timecreated'  => time(),
            'timemodified' => time(),
        ];
        $rating->id = $DB->insert_record('local_datacurso_ratings', $rating);

        $feedback = (object) [
            'feedbacktext' => 'Excellent course',
            'type'         => 'auto',
            'timecreated'  => time(),
            'timemodified' => time(),
        ];
        $feedback->id = $DB->insert_record('local_datacurso_ratings_feedback', $feedback);

        return [
            'rating' => $rating,
            'feedback' => $feedback,
        ];
    }
}
