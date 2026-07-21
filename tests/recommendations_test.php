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
 * Tests for the recommendations service.
 *
 * @package    local_datacurso_ratings
 * @category   test
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_datacurso_ratings;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/external_testcase.php');

/**
 * Tests for the course recommendations algorithm (INT-013).
 *
 * @covers \local_datacurso_ratings\recommendations\service
 */
final class recommendations_test extends \externallib_advanced_testcase {
    /**
     * Insert a rating record directly into the database for test setup.
     *
     * @param int $userid
     * @param int $cmid
     * @param int $courseid
     * @param int $categoryid
     * @param int $rating  1 = like, 0 = dislike
     */
    private function insert_rating(int $userid, int $cmid, int $courseid, int $categoryid, int $rating): void {
        global $DB;
        $now = time();
        $DB->insert_record('local_datacurso_ratings', (object)[
            'userid'       => $userid,
            'cmid'         => $cmid,
            'courseid'     => $courseid,
            'categoryid'   => $categoryid,
            'rating'       => $rating,
            'feedback'     => '',
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Verify that the recommendation score is computed as
     * score = (0.6 * category_preference_pct) + (0.4 * course_satisfaction).
     *
     * Spec: MDL-INT-013 step 1.
     */
    public function test_score_formula_is_applied_correctly(): void {
        $this->resetAfterTest(true);

        $category = $this->getDataGenerator()->create_category();
        $user     = $this->getDataGenerator()->create_user();

        // Create a course the user is NOT enrolled in — it will be a recommendation candidate.
        $targetcourse = $this->getDataGenerator()->create_course(['category' => $category->id, 'visible' => 1]);

        // Create an auxiliary course in the same category that the user rated with a like.
        // This builds the category preference for the user.
        $auxcourse  = $this->getDataGenerator()->create_course(['category' => $category->id, 'visible' => 1]);
        $auxpage    = $this->getDataGenerator()->create_module('page', ['course' => $auxcourse->id]);

        // User likes the auxiliary course activity (builds 100% category preference).
        $this->insert_rating($user->id, $auxpage->cmid, $auxcourse->id, $category->id, 1);

        // Add a like to the target course itself so it has satisfaction data.
        $targetpage  = $this->getDataGenerator()->create_module('page', ['course' => $targetcourse->id]);
        $otherrater  = $this->getDataGenerator()->create_user();
        $this->insert_rating($otherrater->id, $targetpage->cmid, $targetcourse->id, $category->id, 1);

        // Enrol the user in the aux course only (not in target course).
        $this->getDataGenerator()->enrol_user($user->id, $auxcourse->id);

        // Purge cache so the query runs fresh.
        $cache = \cache::make('local_datacurso_ratings', 'recommendations');
        $cache->purge();

        $results = \local_datacurso_ratings\recommendations\service::get_recommendations_for_user($user->id, 10);

        // We need to find the target course in results.
        $found = null;
        foreach ($results as $r) {
            if ((int)$r['courseid'] === (int)$targetcourse->id) {
                $found = $r;
                break;
            }
        }

        $this->assertNotNull($found, 'Target course should appear as a recommendation.');

        // The category_preference_pct is 100 (1 like, 0 dislikes in that category).
        // course_satisfaction = 100 (1 like, 0 dislikes in that course).
        // expected score = (0.6 * 100) + (0.4 * 100) = 100.
        $expectedscore = round((0.6 * $found['category_preference_pct']) + (0.4 * $found['course_satisfaction']), 2);
        $this->assertEquals($expectedscore, (float)$found['score'], 'Score must follow the formula.');
    }

    /**
     * Verify that courses in which the user is already enrolled are excluded from recommendations.
     *
     * Spec: MDL-INT-013 step 2.
     */
    public function test_enrolled_courses_are_excluded(): void {
        $this->resetAfterTest(true);

        $category    = $this->getDataGenerator()->create_category();
        $user        = $this->getDataGenerator()->create_user();
        $enrolledcourse = $this->getDataGenerator()->create_course(['category' => $category->id, 'visible' => 1]);

        $this->getDataGenerator()->enrol_user($user->id, $enrolledcourse->id);

        // Add a like to the enrolled course so it would otherwise score well.
        $page = $this->getDataGenerator()->create_module('page', ['course' => $enrolledcourse->id]);
        $this->insert_rating($user->id, $page->cmid, $enrolledcourse->id, $category->id, 1);

        $cache = \cache::make('local_datacurso_ratings', 'recommendations');
        $cache->purge();

        $results = \local_datacurso_ratings\recommendations\service::get_recommendations_for_user($user->id, 10);

        foreach ($results as $r) {
            $this->assertNotEquals(
                (int)$enrolledcourse->id,
                (int)$r['courseid'],
                'An enrolled course must not appear in recommendations.'
            );
        }
    }

    /**
     * Verify that categories with a user preference ratio below 80% are filtered out.
     *
     * Spec: MDL-INT-013 step 3.
     */
    public function test_low_preference_categories_are_filtered(): void {
        $this->resetAfterTest(true);

        $category = $this->getDataGenerator()->create_category();
        $user     = $this->getDataGenerator()->create_user();

        // Build a low category preference for $user: 1 like and 3 dislikes = 25% preference.
        // Each rating uses a distinct cmid to avoid the UNIQUE(cmid, userid) constraint.
        $auxcourse = $this->getDataGenerator()->create_course(['category' => $category->id, 'visible' => 1]);
        $auxpage1  = $this->getDataGenerator()->create_module('page', ['course' => $auxcourse->id]);
        $auxpage2  = $this->getDataGenerator()->create_module('page', ['course' => $auxcourse->id]);
        $auxpage3  = $this->getDataGenerator()->create_module('page', ['course' => $auxcourse->id]);
        $auxpage4  = $this->getDataGenerator()->create_module('page', ['course' => $auxcourse->id]);
        $this->getDataGenerator()->enrol_user($user->id, $auxcourse->id);

        // User: 1 like + 3 dislikes in this category = 25% preference (below the 80% threshold).
        $this->insert_rating($user->id, $auxpage1->cmid, $auxcourse->id, $category->id, 1);
        $this->insert_rating($user->id, $auxpage2->cmid, $auxcourse->id, $category->id, 0);
        $this->insert_rating($user->id, $auxpage3->cmid, $auxcourse->id, $category->id, 0);
        $this->insert_rating($user->id, $auxpage4->cmid, $auxcourse->id, $category->id, 0);

        // Target course in the same low-preference category (user NOT enrolled).
        $targetcourse = $this->getDataGenerator()->create_course(['category' => $category->id, 'visible' => 1]);

        $cache = \cache::make('local_datacurso_ratings', 'recommendations');
        $cache->purge();

        $results = \local_datacurso_ratings\recommendations\service::get_recommendations_for_user($user->id, 10);

        foreach ($results as $r) {
            $this->assertNotEquals(
                (int)$targetcourse->id,
                (int)$r['courseid'],
                'Courses in low-preference categories (< 80%) must be filtered out.'
            );
        }
    }

    /**
     * Verify that the global rating ratio is used as a fallback when the user has no
     * preference data for a category.
     *
     * Spec: MDL-INT-013 step 4.
     */
    public function test_global_ratio_is_fallback_when_no_category_data(): void {
        $this->resetAfterTest(true);

        $category = $this->getDataGenerator()->create_category();
        $user     = $this->getDataGenerator()->create_user();

        // The user has NO ratings at all — no category preferences.
        // Global ratio will be derived from other users' ratings.
        $course1 = $this->getDataGenerator()->create_course(['category' => $category->id, 'visible' => 1]);

        // Five distinct raters each like a distinct page — global ratio = 1.0 (100%), passes the 80% threshold.
        for ($i = 0; $i < 5; $i++) {
            $rater     = $this->getDataGenerator()->create_user();
            $raterpage = $this->getDataGenerator()->create_module('page', ['course' => $course1->id]);
            $this->insert_rating($rater->id, $raterpage->cmid, $course1->id, $category->id, 1);
        }

        // Target course is in the same category, user is NOT enrolled.
        $targetcourse = $this->getDataGenerator()->create_course(['category' => $category->id, 'visible' => 1]);

        $cache = \cache::make('local_datacurso_ratings', 'recommendations');
        $cache->purge();

        $results = \local_datacurso_ratings\recommendations\service::get_recommendations_for_user($user->id, 10);

        $found = false;
        foreach ($results as $r) {
            if ((int)$r['courseid'] === (int)$targetcourse->id) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Target course should appear when global ratio fallback passes the 80% threshold.');
    }

    /**
     * Verify that the result set is limited to the requested number and sorted by score descending.
     *
     * Spec: MDL-INT-013 step 5.
     */
    public function test_limit_is_respected_and_results_are_sorted_by_score_descending(): void {
        $this->resetAfterTest(true);

        $category = $this->getDataGenerator()->create_category();
        $user     = $this->getDataGenerator()->create_user();

        // Create enough courses that at least 3 are recommendations.
        $courses = [];
        for ($i = 0; $i < 6; $i++) {
            $courses[] = $this->getDataGenerator()->create_course(['category' => $category->id, 'visible' => 1]);
        }

        // Add likes from distinct raters (each with their own page) to build global ratio >= 80%.
        for ($i = 0; $i < 10; $i++) {
            $rater     = $this->getDataGenerator()->create_user();
            $raterpage = $this->getDataGenerator()->create_module('page', ['course' => $courses[0]->id]);
            $this->insert_rating($rater->id, $raterpage->cmid, $courses[0]->id, $category->id, 1);
        }

        // User has no personal category preference — global ratio (100%) acts as fallback.
        $cache = \cache::make('local_datacurso_ratings', 'recommendations');
        $cache->purge();

        $limit   = 3;
        $results = \local_datacurso_ratings\recommendations\service::get_recommendations_for_user($user->id, $limit);

        $this->assertLessThanOrEqual($limit, count($results), 'Results must not exceed the requested limit.');

        // Verify descending order.
        $prev = PHP_INT_MAX;
        foreach ($results as $r) {
            $this->assertLessThanOrEqual(
                $prev,
                (float)$r['score'],
                'Recommendations must be sorted by score in descending order.'
            );
            $prev = (float)$r['score'];
        }
    }
}
