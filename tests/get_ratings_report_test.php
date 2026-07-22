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
 * Tests for get_ratings_report external function.
 *
 * @package    local_datacurso_ratings
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_datacurso_ratings;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/external_testcase.php');

use local_datacurso_ratings\external\get_ratings_report;

/**
 * Test suite for the get_ratings_report web service.
 *
 * @covers \local_datacurso_ratings\external\get_ratings_report
 */
final class get_ratings_report_test extends \externallib_advanced_testcase {
    /**
     * Insert a rating record directly into the plugin table.
     *
     * @param int $cmid
     * @param int $userid
     * @param int $rating  1 = like, 0 = dislike
     * @param string $feedback
     * @param int|null $timemodified  Unix timestamp; defaults to now
     */
    private function insert_rating(int $cmid, int $userid, int $rating, string $feedback = '', ?int $timemodified = null): void {
        global $DB;

        $DB->insert_record('local_datacurso_ratings', (object)[
            'cmid'         => $cmid,
            'userid'       => $userid,
            'courseid'     => 0, // Not relevant for these tests.
            'categoryid'   => 0,
            'rating'       => $rating,
            'feedback'     => $feedback,
            'timecreated'  => $timemodified ?? time(),
            'timemodified' => $timemodified ?? time(),
        ]);
    }

    /**
     * Category filter returns only courses belonging to that category.
     *
     * MDL-INT-004 step 1
     */
    public function test_category_filter_returns_only_courses_in_that_category(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $gen = $this->getDataGenerator();

        $cata = $gen->create_category();
        $catb = $gen->create_category();

        $coursea = $gen->create_course(['category' => $cata->id]);
        $courseb = $gen->create_course(['category' => $catb->id]);

        $quiza = $gen->create_module('quiz', ['course' => $coursea->id]);
        $quizb = $gen->create_module('quiz', ['course' => $courseb->id]);

        $user = $gen->create_user();
        $this->insert_rating($quiza->cmid, $user->id, 1);
        $this->insert_rating($quizb->cmid, $user->id, 1);

        $result = get_ratings_report::execute(0, 25, '', '', (int)$cata->id, '', '');

        $courseids = array_column($result['courses'], 'courseid');
        $this->assertContains((int)$coursea->id, $courseids);
        $this->assertNotContains((int)$courseb->id, $courseids);
    }

    /**
     * Date range filter returns only ratings whose timemodified falls within the period.
     *
     * MDL-INT-004 step 2
     */
    public function test_date_range_filter_returns_only_ratings_within_period(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $quizold = $gen->create_module('quiz', ['course' => $course->id]);
        $quiznew = $gen->create_module('quiz', ['course' => $course->id]);

        $user = $gen->create_user();

        // The "old" rating: 2024-03-01.
        $this->insert_rating($quizold->cmid, $user->id, 1, '', strtotime('2024-03-01 12:00:00'));
        // The "new" rating: 2025-06-15.
        $this->insert_rating($quiznew->cmid, $user->id, 1, '', strtotime('2025-06-15 12:00:00'));

        // Filter to 2025 only.
        $result = get_ratings_report::execute(0, 25, '', '', 0, '2025-01-01', '2025-12-31');

        // Only the 2025 quiz should appear.
        $cmids = [];
        foreach ($result['courses'] as $c) {
            foreach ($c['activities'] as $a) {
                $cmids[] = $a['cmid'];
            }
        }

        $this->assertContains((int)$quiznew->cmid, $cmids);
        $this->assertNotContains((int)$quizold->cmid, $cmids);
    }

    /**
     * Activity name and course name search filters work independently and in combination.
     *
     * MDL-INT-004 step 3
     */
    public function test_activity_and_course_name_search_filters_work(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $gen = $this->getDataGenerator();
        $coursealpha = $gen->create_course(['fullname' => 'Alpha Course']);
        $coursebeta  = $gen->create_course(['fullname' => 'Beta Course']);

        $quizfoo = $gen->create_module('quiz', ['course' => $coursealpha->id, 'name' => 'FooQuiz']);
        $quizbar = $gen->create_module('quiz', ['course' => $coursealpha->id, 'name' => 'BarQuiz']);
        $quizbeta = $gen->create_module('quiz', ['course' => $coursebeta->id, 'name' => 'FooQuiz']);

        $user = $gen->create_user();
        $this->insert_rating($quizfoo->cmid, $user->id, 1);
        $this->insert_rating($quizbar->cmid, $user->id, 1);
        $this->insert_rating($quizbeta->cmid, $user->id, 1);

        // Filter by activity name "Foo".
        $result = get_ratings_report::execute(0, 25, 'Foo', '', 0, '', '');
        $activitynames = [];
        foreach ($result['courses'] as $c) {
            foreach ($c['activities'] as $a) {
                $activitynames[] = $a['activity'];
            }
        }
        $this->assertContains('FooQuiz', $activitynames);
        $this->assertNotContains('BarQuiz', $activitynames);

        // Filter by course name "Alpha".
        $result2 = get_ratings_report::execute(0, 25, '', 'Alpha', 0, '', '');
        $courseids2 = array_column($result2['courses'], 'courseid');
        $this->assertContains((int)$coursealpha->id, $courseids2);
        $this->assertNotContains((int)$coursebeta->id, $courseids2);
    }

    /**
     * Non-allowed perpage values are normalised to the default (5).
     *
     * MDL-INT-004 step 4
     */
    public function test_invalid_perpage_is_normalised_to_default(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Even with no data, the pagination block must reflect normalised perpage = 5.
        $result = get_ratings_report::execute(0, 7, '', '', 0, '', '');

        $this->assertEquals(5, $result['pagination']['perpage']);
    }

    /**
     * Negative page numbers are treated as page zero.
     *
     * MDL-INT-004 step 5
     */
    public function test_negative_page_is_treated_as_page_zero(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $result = get_ratings_report::execute(-3, 5, '', '', 0, '', '');

        $this->assertEquals(0, $result['pagination']['page']);
    }

    /**
     * A page number beyond the last page is clamped to the last available page.
     *
     * MDL-INT-004 step 6
     */
    public function test_page_beyond_total_is_clamped_to_last_page(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $gen = $this->getDataGenerator();
        $user = $gen->create_user();

        // Create 3 courses each with one rated quiz, so totalcourses = 3.
        for ($i = 0; $i < 3; $i++) {
            $course = $gen->create_course();
            $quiz   = $gen->create_module('quiz', ['course' => $course->id]);
            $this->insert_rating($quiz->cmid, $user->id, 1);
        }

        // With perpage=5 there is only 1 page (index 0). Requesting page 99 must clamp to 0.
        $result = get_ratings_report::execute(99, 5, '', '', 0, '', '');

        $this->assertEquals(0, $result['pagination']['page']);
        $this->assertCount(3, $result['courses']);
    }

    /**
     * The global summary correctly aggregates likes, dislikes, and satisfaction metrics.
     *
     * MDL-INT-004 step 7
     */
    public function test_global_summary_aggregates_metrics_correctly(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $quiza  = $gen->create_module('quiz', ['course' => $course->id]);
        $quizb  = $gen->create_module('quiz', ['course' => $course->id]);

        $user1 = $gen->create_user();
        $user2 = $gen->create_user();
        $user3 = $gen->create_user();

        // 2 likes and 1 dislike for quiza.
        $this->insert_rating($quiza->cmid, $user1->id, 1);
        $this->insert_rating($quiza->cmid, $user2->id, 1);
        $this->insert_rating($quiza->cmid, $user3->id, 0);

        // 1 like for quizb.
        $this->insert_rating($quizb->cmid, $user1->id, 1);

        $result = get_ratings_report::execute(0, 25, '', '', 0, '', '');
        $summary = $result['summary'];

        $this->assertEquals(3, $summary['total_likes']);
        $this->assertEquals(1, $summary['total_dislikes']);
        $this->assertEquals(4, $summary['total_ratings']);
        $this->assertEquals(2, $summary['activities_with_ratings']);
        $this->assertIsString($summary['overall_satisfaction']);
        // 3 likes / 4 total = 75.0 %.
        $this->assertEquals('75.0', $summary['overall_satisfaction']);
        $this->assertContains($summary['satisfaction_class'], ['success', 'warning', 'info', 'danger']);
        $this->assertArrayHasKey('total_courses', $summary);
        $this->assertArrayHasKey('total_activities', $summary);
    }
}
