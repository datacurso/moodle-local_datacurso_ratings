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
 * Tests for get_activity_comments external function.
 *
 * @package    local_datacurso_ratings
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_datacurso_ratings\external\get_activity_comments
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/external_testcase.php');

use local_datacurso_ratings\external\get_activity_comments;

/**
 * Test suite for the get_activity_comments web service.
 */
class get_activity_comments_test extends externallib_advanced_testcase {

    /**
     * Insert a rating with feedback into the plugin table.
     *
     * @param int $cmid
     * @param int $userid
     * @param int $rating  1 = like, 0 = dislike
     * @param string $feedback
     */
    private function insert_rating(int $cmid, int $userid, int $rating, string $feedback = ''): void {
        global $DB;

        $DB->insert_record('local_datacurso_ratings', (object)[
            'cmid'         => $cmid,
            'userid'       => $userid,
            'courseid'     => 0,
            'categoryid'   => 0,
            'rating'       => $rating,
            'feedback'     => $feedback,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Statistics correctly count positive and negative comments, average length, and keywords.
     *
     * MDL-INT-006 step 1
     */
    public function test_statistics_count_positive_negative_and_keywords(): void {
        $this->resetAfterTest(true);

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $quiz   = $gen->create_module('quiz', ['course' => $course->id]);

        // Assign viewcoursereport to the calling user.
        $role = $gen->create_role();
        assign_capability('local/datacurso_ratings:viewcoursereport', CAP_ALLOW,
            $role, context_module::instance($quiz->cmid));
        $caller = $gen->create_user();
        $gen->enrol_user($caller->id, $course->id);
        role_assign($role, $caller->id, context_module::instance($quiz->cmid));
        $this->setUser($caller);

        $u1 = $gen->create_user();
        $u2 = $gen->create_user();
        $u3 = $gen->create_user();

        // 2 positive, 1 negative — each with meaningful text.
        $this->insert_rating($quiz->cmid, $u1->id, 1, 'excellent learning content overall');
        $this->insert_rating($quiz->cmid, $u2->id, 1, 'learning content really good stuff');
        $this->insert_rating($quiz->cmid, $u3->id, 0, 'content too long needs improvement');

        $result = get_activity_comments::execute($quiz->cmid, 0, 20, '');
        $stats  = $result['statistics'];

        $this->assertEquals(3, $stats['total_comments']);
        $this->assertEquals(2, $stats['like_comments']);
        $this->assertEquals(1, $stats['dislike_comments']);
        $this->assertGreaterThan(0, $stats['avg_length']);

        // "content" appears in all three comments and should surface as a keyword.
        $words = array_column($stats['keywords'], 'word');
        $this->assertContains('content', $words);
    }

    /**
     * Statistics return zero values without error when an activity has no comments.
     *
     * MDL-INT-006 step 2
     */
    public function test_empty_activity_statistics_return_zero_values(): void {
        $this->resetAfterTest(true);

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $quiz   = $gen->create_module('quiz', ['course' => $course->id]);

        $role = $gen->create_role();
        assign_capability('local/datacurso_ratings:viewcoursereport', CAP_ALLOW,
            $role, context_module::instance($quiz->cmid));
        $caller = $gen->create_user();
        $gen->enrol_user($caller->id, $course->id);
        role_assign($role, $caller->id, context_module::instance($quiz->cmid));
        $this->setUser($caller);

        $result = get_activity_comments::execute($quiz->cmid, 0, 20, '');
        $stats  = $result['statistics'];

        $this->assertEquals(0, $stats['total_comments']);
        $this->assertEquals(0, $stats['like_comments']);
        $this->assertEquals(0, $stats['dislike_comments']);
        $this->assertEquals(0, $stats['avg_length']);
        $this->assertIsArray($stats['keywords']);
        $this->assertEmpty($stats['keywords']);
    }

    /**
     * Pagination reports hasmore = true when there are more comments beyond the current page.
     *
     * MDL-INT-006 step 3
     */
    public function test_pagination_hasmore_is_true_when_more_pages_exist(): void {
        $this->resetAfterTest(true);

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $quiz   = $gen->create_module('quiz', ['course' => $course->id]);

        $role = $gen->create_role();
        assign_capability('local/datacurso_ratings:viewcoursereport', CAP_ALLOW,
            $role, context_module::instance($quiz->cmid));
        $caller = $gen->create_user();
        $gen->enrol_user($caller->id, $course->id);
        role_assign($role, $caller->id, context_module::instance($quiz->cmid));
        $this->setUser($caller);

        // Insert 5 comments but request only 2 per page.
        for ($i = 1; $i <= 5; $i++) {
            $u = $gen->create_user();
            $this->insert_rating($quiz->cmid, $u->id, 1, "comment number $i with enough text");
        }

        $result = get_activity_comments::execute($quiz->cmid, 0, 2, '');
        $pagination = $result['pagination'];

        $this->assertEquals(5, $pagination['total']);
        $this->assertTrue((bool)$pagination['hasmore']);
        $this->assertGreaterThan(1, $pagination['totalpages']);

        // Last page should NOT have more.
        $lastPage = $pagination['totalpages'] - 1;
        $resultLast = get_activity_comments::execute($quiz->cmid, $lastPage, 2, '');
        $this->assertFalse((bool)$resultLast['pagination']['hasmore']);
    }

    /**
     * Search text filters comments to only those containing the search term.
     *
     * MDL-INT-006 step 4
     */
    public function test_search_text_filters_comments_by_term(): void {
        $this->resetAfterTest(true);

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $quiz   = $gen->create_module('quiz', ['course' => $course->id]);

        $role = $gen->create_role();
        assign_capability('local/datacurso_ratings:viewcoursereport', CAP_ALLOW,
            $role, context_module::instance($quiz->cmid));
        $caller = $gen->create_user();
        $gen->enrol_user($caller->id, $course->id);
        role_assign($role, $caller->id, context_module::instance($quiz->cmid));
        $this->setUser($caller);

        $u1 = $gen->create_user();
        $u2 = $gen->create_user();

        $this->insert_rating($quiz->cmid, $u1->id, 1, 'excellent teaching material provided');
        $this->insert_rating($quiz->cmid, $u2->id, 0, 'confusing interface hard navigation');

        // Search for "excellent" — should return only the first comment.
        $result = get_activity_comments::execute($quiz->cmid, 0, 20, 'excellent');
        $comments = $result['comments'];

        $this->assertCount(1, $comments);
        $this->assertStringContainsStringIgnoringCase('excellent', $comments[0]['feedback']);
    }
}
