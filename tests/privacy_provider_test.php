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
 */

namespace local_datacurso_ratings;

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\types\database_table;
use core_privacy\local\metadata\types\external_location;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_datacurso_ratings\privacy\provider;

/**
 * Tests for GDPR compliance: metadata, context discovery, export, and selective deletes (INT-015).
 *
 * Every rating belongs to the module context of the rated course module, so all
 * contextlist, userlist, export and delete operations are scoped to CONTEXT_MODULE.
 *
 * @covers \local_datacurso_ratings\privacy\provider
 */
final class privacy_provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Insert a rating record directly for the given user and course module.
     *
     * @param int $userid
     * @param \stdClass $module Module record returned by the generator (has cmid and course).
     * @param int $rating 1 = like, 0 = dislike.
     * @param string $feedback
     * @param int $time Timestamp used for timecreated and timemodified.
     * @return int Rating record id.
     */
    private function insert_rating(int $userid, \stdClass $module, int $rating, string $feedback, int $time): int {
        global $DB;
        return $DB->insert_record('local_datacurso_ratings', (object)[
            'userid'       => $userid,
            'cmid'         => $module->cmid,
            'courseid'     => $module->course,
            'categoryid'   => 1,
            'rating'       => $rating,
            'feedback'     => $feedback,
            'timecreated'  => $time,
            'timemodified' => $time,
        ]);
    }

    /**
     * Insert a predefined feedback phrase (admin configuration, no personal data).
     *
     * @param string $text
     * @param string $type 'like' or 'dislike'.
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
     * Create a course with a page and a forum activity plus two enrolled students.
     *
     * @return array{course: \stdClass, page: \stdClass, forum: \stdClass, student1: \stdClass, student2: \stdClass}
     */
    private function create_fixture(): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $page = $generator->create_module('page', ['course' => $course->id]);
        $forum = $generator->create_module('forum', ['course' => $course->id]);
        $student1 = $generator->create_user();
        $student2 = $generator->create_user();
        $generator->enrol_user($student1->id, $course->id, 'student');
        $generator->enrol_user($student2->id, $course->id, 'student');

        return [
            'course' => $course,
            'page' => $page,
            'forum' => $forum,
            'student1' => $student1,
            'student2' => $student2,
        ];
    }

    /**
     * The subcontext under which ratings are exported.
     *
     * @return string[]
     */
    private function subcontext(): array {
        return [get_string('privacy:subcontext:ratings', 'local_datacurso_ratings')];
    }

    /**
     * The metadata collection must describe the ratings table and the external AI service.
     */
    public function test_get_metadata_declares_table_and_external_ai_location(): void {
        $collection = provider::get_metadata(new collection('local_datacurso_ratings'));
        $items = $collection->get_collection();

        $tables = array_values(array_filter($items, static fn($item) => $item instanceof database_table));
        $externals = array_values(array_filter($items, static fn($item) => $item instanceof external_location));

        $this->assertCount(1, $tables);
        $this->assertSame('local_datacurso_ratings', $tables[0]->get_name());
        $this->assertArrayHasKey('feedback', $tables[0]->get_privacy_fields());

        $this->assertCount(1, $externals, 'The Datacurso AI service must be declared as an external location.');
        $this->assertSame('datacurso_ai', $externals[0]->get_name());
        $this->assertSame('privacy:metadata:datacurso_ai', $externals[0]->get_summary());
        $fields = $externals[0]->get_privacy_fields();
        foreach (['feedback', 'course', 'activity', 'activity_type', 'approvalpercent', 'userid'] as $field) {
            $this->assertArrayHasKey($field, $fields);
            $this->assertSame('privacy:metadata:datacurso_ai:' . $field, $fields[$field]);
        }
    }

    /**
     * The contexts for a user are exactly the module contexts of the course modules the user rated.
     */
    public function test_get_contexts_for_userid_returns_only_rated_module_contexts(): void {
        $this->resetAfterTest(true);
        ['page' => $page, 'forum' => $forum, 'student1' => $student1, 'student2' => $student2] = $this->create_fixture();
        $nobody = $this->getDataGenerator()->create_user();
        $now = time();

        $this->insert_rating($student1->id, $page, 1, 'Page feedback', $now);
        $this->insert_rating($student1->id, $forum, 0, 'Forum feedback', $now);
        $this->insert_rating($student2->id, $page, 1, 'Other feedback', $now);

        $pagectx = \context_module::instance($page->cmid);
        $forumctx = \context_module::instance($forum->cmid);

        $contextids = provider::get_contexts_for_userid($student1->id)->get_contextids();
        sort($contextids);
        $expected = [$pagectx->id, $forumctx->id];
        sort($expected);
        $this->assertSame($expected, array_map('intval', $contextids));
        $this->assertNotContains(\context_system::instance()->id, array_map('intval', $contextids));

        $contextids = provider::get_contexts_for_userid($student2->id)->get_contextids();
        $this->assertSame([$pagectx->id], array_map('intval', $contextids));

        $this->assertEmpty(provider::get_contexts_for_userid($nobody->id)->get_contextids());
    }

    /**
     * A module context lists only the users who rated that module; other context levels list nobody.
     */
    public function test_get_users_in_context_returns_only_raters_of_that_module(): void {
        $this->resetAfterTest(true);
        ['course' => $course, 'page' => $page, 'forum' => $forum, 'student1' => $student1, 'student2' => $student2] =
            $this->create_fixture();
        $now = time();

        $this->insert_rating($student1->id, $page, 1, 'Page feedback', $now);
        $this->insert_rating($student2->id, $page, 0, 'Other feedback', $now);
        $this->insert_rating($student1->id, $forum, 1, 'Forum feedback', $now);

        $userlist = new userlist(\context_module::instance($page->cmid), 'local_datacurso_ratings');
        provider::get_users_in_context($userlist);
        $userids = $userlist->get_userids();
        sort($userids);
        $expected = [$student1->id, $student2->id];
        sort($expected);
        $this->assertSame($expected, array_map('intval', $userids));

        $userlist = new userlist(\context_module::instance($forum->cmid), 'local_datacurso_ratings');
        provider::get_users_in_context($userlist);
        $this->assertSame([$student1->id], array_map('intval', $userlist->get_userids()));

        $userlist = new userlist(\context_course::instance($course->id), 'local_datacurso_ratings');
        provider::get_users_in_context($userlist);
        $this->assertEmpty($userlist->get_userids(), 'A course context must not list raters.');

        $userlist = new userlist(\context_system::instance(), 'local_datacurso_ratings');
        provider::get_users_in_context($userlist);
        $this->assertEmpty($userlist->get_userids(), 'The system context must not list raters.');
    }

    /**
     * Export writes only the rating of the approved module context, in a human readable form.
     */
    public function test_export_user_data_exports_only_approved_module_context(): void {
        $this->resetAfterTest(true);
        ['page' => $page, 'forum' => $forum, 'student1' => $student1, 'student2' => $student2] = $this->create_fixture();
        $created = 1700000000;
        $modified = 1700003600;

        global $DB;
        $ratingid = $this->insert_rating($student1->id, $page, 1, 'Loved the page', $created);
        $DB->set_field('local_datacurso_ratings', 'timemodified', $modified, ['id' => $ratingid]);
        $this->insert_rating($student1->id, $forum, 0, 'Forum was confusing', $created);
        $this->insert_rating($student2->id, $page, 0, 'Not for me', $created);

        $pagectx = \context_module::instance($page->cmid);
        $forumctx = \context_module::instance($forum->cmid);

        $approved = new approved_contextlist($student1, 'local_datacurso_ratings', [$pagectx->id]);
        provider::export_user_data($approved);

        $data = writer::with_context($pagectx)->get_data($this->subcontext());
        $this->assertNotEmpty($data, 'The approved module context must contain exported data.');
        $this->assertSame(get_string('like', 'local_datacurso_ratings'), $data->rating);
        $this->assertSame('Loved the page', $data->feedback);
        $this->assertSame(transform::datetime($created), $data->timecreated);
        $this->assertSame(transform::datetime($modified), $data->timemodified);
        $this->assertObjectNotHasProperty('userid', $data, 'Internal ids must not be exported.');
        $this->assertObjectNotHasProperty('cmid', $data, 'Internal ids must not be exported.');

        $this->assertFalse(
            writer::with_context($forumctx)->has_any_data(),
            'A module context that was not approved must not receive any data.'
        );
        $this->assertFalse(
            writer::with_context(\context_system::instance())->has_any_data(),
            'The system context must not receive any data.'
        );
    }

    /**
     * A dislike is exported with its readable label.
     */
    public function test_export_user_data_exports_dislike_label(): void {
        $this->resetAfterTest(true);
        ['forum' => $forum, 'student1' => $student1] = $this->create_fixture();
        $this->insert_rating($student1->id, $forum, 0, 'Too long', time());
        $forumctx = \context_module::instance($forum->cmid);

        provider::export_user_data(new approved_contextlist($student1, 'local_datacurso_ratings', [$forumctx->id]));

        $data = writer::with_context($forumctx)->get_data($this->subcontext());
        $this->assertSame(get_string('dislike', 'local_datacurso_ratings'), $data->rating);
    }

    /**
     * Deleting a module context removes every rating of that module and nothing else.
     */
    public function test_delete_data_for_all_users_in_context_deletes_only_that_module(): void {
        global $DB;
        $this->resetAfterTest(true);
        ['course' => $course, 'page' => $page, 'forum' => $forum, 'student1' => $student1, 'student2' => $student2] =
            $this->create_fixture();
        $now = time();

        $this->insert_rating($student1->id, $page, 1, 'Page feedback', $now);
        $this->insert_rating($student2->id, $page, 0, 'Other feedback', $now);
        $this->insert_rating($student1->id, $forum, 1, 'Forum feedback', $now);

        provider::delete_data_for_all_users_in_context(\context_course::instance($course->id));
        $this->assertEquals(3, $DB->count_records('local_datacurso_ratings'), 'A course context must delete nothing.');

        provider::delete_data_for_all_users_in_context(\context_system::instance());
        $this->assertEquals(3, $DB->count_records('local_datacurso_ratings'), 'The system context must delete nothing.');

        provider::delete_data_for_all_users_in_context(\context_module::instance($page->cmid));
        $this->assertEquals(0, $DB->count_records('local_datacurso_ratings', ['cmid' => $page->cmid]));
        $this->assertEquals(1, $DB->count_records('local_datacurso_ratings', ['cmid' => $forum->cmid]));
    }

    /**
     * Deleting a user's data only removes the rows in the approved module contexts.
     */
    public function test_delete_data_for_user_deletes_only_approved_contexts(): void {
        global $DB;
        $this->resetAfterTest(true);
        ['page' => $page, 'forum' => $forum, 'student1' => $student1, 'student2' => $student2] = $this->create_fixture();
        $now = time();

        $this->insert_rating($student1->id, $page, 1, 'Page feedback', $now);
        $this->insert_rating($student1->id, $forum, 0, 'Forum feedback', $now);
        $this->insert_rating($student2->id, $page, 1, 'Other feedback', $now);

        $pagectx = \context_module::instance($page->cmid);
        provider::delete_data_for_user(new approved_contextlist($student1, 'local_datacurso_ratings', [$pagectx->id]));

        $this->assertFalse($DB->record_exists('local_datacurso_ratings', ['userid' => $student1->id, 'cmid' => $page->cmid]));
        $this->assertTrue(
            $DB->record_exists('local_datacurso_ratings', ['userid' => $student1->id, 'cmid' => $forum->cmid]),
            'Ratings in contexts that were not approved must remain.'
        );
        $this->assertTrue(
            $DB->record_exists('local_datacurso_ratings', ['userid' => $student2->id, 'cmid' => $page->cmid]),
            'Other users\' ratings must remain untouched.'
        );
    }

    /**
     * Deleting an approved userlist only removes the listed users' rows in that module.
     */
    public function test_delete_data_for_users_deletes_only_listed_users_in_module(): void {
        global $DB;
        $this->resetAfterTest(true);
        ['course' => $course, 'page' => $page, 'forum' => $forum, 'student1' => $student1, 'student2' => $student2] =
            $this->create_fixture();
        $now = time();

        $this->insert_rating($student1->id, $page, 1, 'Page feedback', $now);
        $this->insert_rating($student2->id, $page, 0, 'Other feedback', $now);
        $this->insert_rating($student1->id, $forum, 1, 'Forum feedback', $now);

        $coursectx = \context_course::instance($course->id);
        provider::delete_data_for_users(new approved_userlist($coursectx, 'local_datacurso_ratings', [$student1->id]));
        $this->assertEquals(3, $DB->count_records('local_datacurso_ratings'), 'A course context must delete nothing.');

        $pagectx = \context_module::instance($page->cmid);
        provider::delete_data_for_users(new approved_userlist($pagectx, 'local_datacurso_ratings', [$student1->id]));

        $this->assertFalse($DB->record_exists('local_datacurso_ratings', ['userid' => $student1->id, 'cmid' => $page->cmid]));
        $this->assertTrue($DB->record_exists('local_datacurso_ratings', ['userid' => $student2->id, 'cmid' => $page->cmid]));
        $this->assertTrue($DB->record_exists('local_datacurso_ratings', ['userid' => $student1->id, 'cmid' => $forum->cmid]));
    }

    /**
     * Predefined feedback phrases are admin site configuration, not personal data:
     * no privacy delete may touch them.
     */
    public function test_privacy_deletes_preserve_feedback_phrases(): void {
        global $DB;
        $this->resetAfterTest(true);
        ['page' => $page, 'student1' => $student1, 'student2' => $student2] = $this->create_fixture();
        $now = time();

        $this->insert_rating($student1->id, $page, 1, 'Page feedback', $now);
        $this->insert_rating($student2->id, $page, 0, 'Other feedback', $now);
        $this->insert_feedback_phrase('Great activity!', 'like');
        $this->insert_feedback_phrase('Too long', 'dislike');

        $pagectx = \context_module::instance($page->cmid);
        provider::delete_data_for_users(new approved_userlist($pagectx, 'local_datacurso_ratings', [$student2->id]));
        provider::delete_data_for_user(new approved_contextlist($student1, 'local_datacurso_ratings', [$pagectx->id]));
        provider::delete_data_for_all_users_in_context($pagectx);

        $this->assertEquals(0, $DB->count_records('local_datacurso_ratings'));
        $this->assertEquals(
            2,
            $DB->count_records('local_datacurso_ratings_feedback'),
            'Predefined feedback phrases must survive every privacy delete.'
        );
    }
}
