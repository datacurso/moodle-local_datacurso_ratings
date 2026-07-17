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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/external_testcase.php');

use local_datacurso_ratings\external\get_ratings_report;
use local_datacurso_ratings\external\get_activity_comments;

/**
 * Tests for helper logic in get_ratings_report and get_activity_comments,
 * exercised entirely through their public execute() methods.
 *
 * Covers: MDL-UNIT-001, MDL-UNIT-002, MDL-UNIT-003, MDL-UNIT-004.
 *
 * @package    local_datacurso_ratings
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \local_datacurso_ratings\external\get_ratings_report
 * @covers \local_datacurso_ratings\external\get_activity_comments
 */
class helpers_test extends \externallib_advanced_testcase {

    // -------------------------------------------------------------------------
    // Shared helpers
    // -------------------------------------------------------------------------

    /**
     * Insert a rating record directly into the plugin table.
     *
     * @param int $cmid
     * @param int $userid
     * @param int $rating         1 = like, 0 = dislike
     * @param string $feedback
     * @param int|null $timemodified  Unix timestamp; defaults to now
     */
    private function insert_rating(
        int $cmid,
        int $userid,
        int $rating,
        string $feedback = '',
        ?int $timemodified = null
    ): void {
        global $DB;

        $DB->insert_record('local_datacurso_ratings', (object)[
            'cmid'         => $cmid,
            'userid'       => $userid,
            'courseid'     => 0,
            'categoryid'   => 0,
            'rating'       => $rating,
            'feedback'     => $feedback,
            'timecreated'  => $timemodified ?? time(),
            'timemodified' => $timemodified ?? time(),
        ]);
    }

    /**
     * Return the satisfaction_class for the first activity in the first course
     * returned by get_ratings_report::execute() with no filters.
     *
     * Assumes a single course + single activity was created for this test.
     *
     * @return string
     */
    private function first_activity_satisfaction_class(): string {
        $result = get_ratings_report::execute(0, 5, '', '', 0, '', '');
        return $result['courses'][0]['activities'][0]['satisfaction_class'];
    }

    // -------------------------------------------------------------------------
    // MDL-UNIT-001 — Satisfaction classification through execute()
    // -------------------------------------------------------------------------

    /**
     * Activity with 80% likes produces satisfaction_class = 'success'.
     *
     * @covers \local_datacurso_ratings\external\get_ratings_report
     * MDL-UNIT-001
     */
    public function test_satisfaction_class_is_success_at_eighty_percent(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $gen    = $this->getDataGenerator();
        $course = $gen->create_course();
        $quiz   = $gen->create_module('quiz', ['course' => $course->id]);

        // 4 likes + 1 dislike = 80.0 % → success.
        $users = [];
        for ($i = 0; $i < 5; $i++) {
            $users[] = $gen->create_user();
        }
        $this->insert_rating($quiz->cmid, $users[0]->id, 1);
        $this->insert_rating($quiz->cmid, $users[1]->id, 1);
        $this->insert_rating($quiz->cmid, $users[2]->id, 1);
        $this->insert_rating($quiz->cmid, $users[3]->id, 1);
        $this->insert_rating($quiz->cmid, $users[4]->id, 0);

        $this->assertSame('success', $this->first_activity_satisfaction_class());
    }

    /**
     * Activity with 100% likes produces satisfaction_class = 'success'.
     *
     * @covers \local_datacurso_ratings\external\get_ratings_report
     * MDL-UNIT-001
     */
    public function test_satisfaction_class_is_success_at_one_hundred_percent(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $gen    = $this->getDataGenerator();
        $course = $gen->create_course();
        $quiz   = $gen->create_module('quiz', ['course' => $course->id]);

        // 3 likes + 0 dislikes = 100.0 % → success.
        for ($i = 0; $i < 3; $i++) {
            $user = $gen->create_user();
            $this->insert_rating($quiz->cmid, $user->id, 1);
        }

        $this->assertSame('success', $this->first_activity_satisfaction_class());
    }

    /**
     * Activity with 60% likes produces satisfaction_class = 'warning'.
     *
     * @covers \local_datacurso_ratings\external\get_ratings_report
     * MDL-UNIT-001
     */
    public function test_satisfaction_class_is_warning_at_sixty_percent(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $gen    = $this->getDataGenerator();
        $course = $gen->create_course();
        $quiz   = $gen->create_module('quiz', ['course' => $course->id]);

        // 3 likes + 2 dislikes = 60.0 % → warning.
        $users = [];
        for ($i = 0; $i < 5; $i++) {
            $users[] = $gen->create_user();
        }
        $this->insert_rating($quiz->cmid, $users[0]->id, 1);
        $this->insert_rating($quiz->cmid, $users[1]->id, 1);
        $this->insert_rating($quiz->cmid, $users[2]->id, 1);
        $this->insert_rating($quiz->cmid, $users[3]->id, 0);
        $this->insert_rating($quiz->cmid, $users[4]->id, 0);

        $this->assertSame('warning', $this->first_activity_satisfaction_class());
    }

    /**
     * Activity with 70% likes (above the warning floor, below the success floor) stays 'warning'.
     *
     * @covers \local_datacurso_ratings\external\get_ratings_report
     * MDL-UNIT-001
     */
    public function test_satisfaction_class_is_warning_at_seventy_percent(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $gen    = $this->getDataGenerator();
        $course = $gen->create_course();
        $quiz   = $gen->create_module('quiz', ['course' => $course->id]);

        // 7 likes + 3 dislikes = 70.0 % → warning.
        $users = [];
        for ($i = 0; $i < 10; $i++) {
            $users[] = $gen->create_user();
        }
        for ($i = 0; $i < 7; $i++) {
            $this->insert_rating($quiz->cmid, $users[$i]->id, 1);
        }
        for ($i = 7; $i < 10; $i++) {
            $this->insert_rating($quiz->cmid, $users[$i]->id, 0);
        }

        $this->assertSame('warning', $this->first_activity_satisfaction_class());
    }

    /**
     * Activity with 40% likes produces satisfaction_class = 'info'.
     *
     * @covers \local_datacurso_ratings\external\get_ratings_report
     * MDL-UNIT-001
     */
    public function test_satisfaction_class_is_info_at_forty_percent(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $gen    = $this->getDataGenerator();
        $course = $gen->create_course();
        $quiz   = $gen->create_module('quiz', ['course' => $course->id]);

        // 2 likes + 3 dislikes = 40.0 % → info.
        $users = [];
        for ($i = 0; $i < 5; $i++) {
            $users[] = $gen->create_user();
        }
        $this->insert_rating($quiz->cmid, $users[0]->id, 1);
        $this->insert_rating($quiz->cmid, $users[1]->id, 1);
        $this->insert_rating($quiz->cmid, $users[2]->id, 0);
        $this->insert_rating($quiz->cmid, $users[3]->id, 0);
        $this->insert_rating($quiz->cmid, $users[4]->id, 0);

        $this->assertSame('info', $this->first_activity_satisfaction_class());
    }

    /**
     * Activity with 50% likes (above the info floor, below the warning floor) stays 'info'.
     *
     * @covers \local_datacurso_ratings\external\get_ratings_report
     * MDL-UNIT-001
     */
    public function test_satisfaction_class_is_info_at_fifty_percent(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $gen    = $this->getDataGenerator();
        $course = $gen->create_course();
        $quiz   = $gen->create_module('quiz', ['course' => $course->id]);

        // 1 like + 1 dislike = 50.0 % → info.
        $u1 = $gen->create_user();
        $u2 = $gen->create_user();
        $this->insert_rating($quiz->cmid, $u1->id, 1);
        $this->insert_rating($quiz->cmid, $u2->id, 0);

        $this->assertSame('info', $this->first_activity_satisfaction_class());
    }

    /**
     * Activity with 20% likes produces satisfaction_class = 'danger'.
     *
     * @covers \local_datacurso_ratings\external\get_ratings_report
     * MDL-UNIT-001
     */
    public function test_satisfaction_class_is_danger_at_twenty_percent(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $gen    = $this->getDataGenerator();
        $course = $gen->create_course();
        $quiz   = $gen->create_module('quiz', ['course' => $course->id]);

        // 1 like + 4 dislikes = 20.0 % → danger.
        $users = [];
        for ($i = 0; $i < 5; $i++) {
            $users[] = $gen->create_user();
        }
        $this->insert_rating($quiz->cmid, $users[0]->id, 1);
        $this->insert_rating($quiz->cmid, $users[1]->id, 0);
        $this->insert_rating($quiz->cmid, $users[2]->id, 0);
        $this->insert_rating($quiz->cmid, $users[3]->id, 0);
        $this->insert_rating($quiz->cmid, $users[4]->id, 0);

        $this->assertSame('danger', $this->first_activity_satisfaction_class());
    }

    /**
     * Activity with 0% likes (all dislikes) produces satisfaction_class = 'danger'.
     *
     * @covers \local_datacurso_ratings\external\get_ratings_report
     * MDL-UNIT-001
     */
    public function test_satisfaction_class_is_danger_at_zero_percent(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $gen    = $this->getDataGenerator();
        $course = $gen->create_course();
        $quiz   = $gen->create_module('quiz', ['course' => $course->id]);

        // 0 likes + 3 dislikes = 0.0 % → danger.
        for ($i = 0; $i < 3; $i++) {
            $user = $gen->create_user();
            $this->insert_rating($quiz->cmid, $user->id, 0);
        }

        $this->assertSame('danger', $this->first_activity_satisfaction_class());
    }

    /**
     * courseSatisfactionClass on each course also reflects the satisfaction classification.
     *
     * This confirms get_satisfaction_class() is also applied to course-level satisfaction,
     * not only to individual activities.
     *
     * @covers \local_datacurso_ratings\external\get_ratings_report
     * MDL-UNIT-001
     */
    public function test_course_satisfaction_class_reflects_aggregate_rating(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $gen    = $this->getDataGenerator();
        $course = $gen->create_course();
        $quiz   = $gen->create_module('quiz', ['course' => $course->id]);

        // 4 likes + 1 dislike = 80 % → success at both activity and course level.
        $users = [];
        for ($i = 0; $i < 5; $i++) {
            $users[] = $gen->create_user();
        }
        $this->insert_rating($quiz->cmid, $users[0]->id, 1);
        $this->insert_rating($quiz->cmid, $users[1]->id, 1);
        $this->insert_rating($quiz->cmid, $users[2]->id, 1);
        $this->insert_rating($quiz->cmid, $users[3]->id, 1);
        $this->insert_rating($quiz->cmid, $users[4]->id, 0);

        $result = get_ratings_report::execute(0, 5, '', '', 0, '', '');
        $this->assertSame('success', $result['courses'][0]['courseSatisfactionClass']);
    }

    // -------------------------------------------------------------------------
    // MDL-UNIT-002 — Date parsing through execute()
    // -------------------------------------------------------------------------

    /**
     * Datefrom filter applied to start of day retains ratings timestamped on that day.
     *
     * MDL-UNIT-002
     */
    public function test_datefrom_start_of_day_retains_same_day_rating(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $gen    = $this->getDataGenerator();
        $course = $gen->create_course();
        $quiz   = $gen->create_module('quiz', ['course' => $course->id]);

        $user    = $gen->create_user();
        $midnight = strtotime('2025-06-15 00:00:00');
        $this->insert_rating($quiz->cmid, $user->id, 1, '', $midnight);

        // Filter from 2025-06-15 — the rating at 00:00:00 must be included.
        $result = get_ratings_report::execute(0, 25, '', '', 0, '2025-06-15', '');

        $this->assertTrue($result['has_data'], 'Rating at start of day must be included by datefrom filter.');
    }

    /**
     * Datefrom filter excludes ratings timestamped before the start of the specified date.
     *
     * MDL-UNIT-002
     */
    public function test_datefrom_excludes_ratings_from_previous_day(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $gen      = $this->getDataGenerator();
        $course   = $gen->create_course();
        $quizOld  = $gen->create_module('quiz', ['course' => $course->id]);
        $quizNew  = $gen->create_module('quiz', ['course' => $course->id]);

        $user = $gen->create_user();
        // Old rating: day before the filter start.
        $this->insert_rating($quizOld->cmid, $user->id, 1, '', strtotime('2025-06-14 23:59:59'));
        // New rating: first second of filter day.
        $this->insert_rating($quizNew->cmid, $user->id, 1, '', strtotime('2025-06-15 00:00:00'));

        $result = get_ratings_report::execute(0, 25, '', '', 0, '2025-06-15', '');

        $cmids = [];
        foreach ($result['courses'] as $c) {
            foreach ($c['activities'] as $a) {
                $cmids[] = $a['cmid'];
            }
        }
        $this->assertContains((int)$quizNew->cmid, $cmids, 'Rating on filter start day must appear.');
        $this->assertNotContains((int)$quizOld->cmid, $cmids, 'Rating from day before must be excluded.');
    }

    /**
     * Dateto filter applied to end of day retains ratings timestamped at 23:59:59 on that day.
     *
     * MDL-UNIT-002
     */
    public function test_dateto_end_of_day_retains_last_second_rating(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $gen    = $this->getDataGenerator();
        $course = $gen->create_course();
        $quiz   = $gen->create_module('quiz', ['course' => $course->id]);

        $user       = $gen->create_user();
        $endOfDay   = strtotime('2025-06-15 23:59:59');
        $this->insert_rating($quiz->cmid, $user->id, 1, '', $endOfDay);

        // Filter up to 2025-06-15 — the rating at 23:59:59 must be included.
        $result = get_ratings_report::execute(0, 25, '', '', 0, '', '2025-06-15');

        $this->assertTrue($result['has_data'], 'Rating at end of day must be included by dateto filter.');
    }

    /**
     * Dateto filter excludes ratings from the day after the specified end date.
     *
     * MDL-UNIT-002
     */
    public function test_dateto_excludes_ratings_from_next_day(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $gen         = $this->getDataGenerator();
        $course      = $gen->create_course();
        $quizBefore  = $gen->create_module('quiz', ['course' => $course->id]);
        $quizAfter   = $gen->create_module('quiz', ['course' => $course->id]);

        $user = $gen->create_user();
        // Rating on the allowed day.
        $this->insert_rating($quizBefore->cmid, $user->id, 1, '', strtotime('2025-06-15 12:00:00'));
        // Rating on the day after the end of the filter range.
        $this->insert_rating($quizAfter->cmid, $user->id, 1, '', strtotime('2025-06-16 00:00:00'));

        $result = get_ratings_report::execute(0, 25, '', '', 0, '', '2025-06-15');

        $cmids = [];
        foreach ($result['courses'] as $c) {
            foreach ($c['activities'] as $a) {
                $cmids[] = $a['cmid'];
            }
        }
        $this->assertContains((int)$quizBefore->cmid, $cmids, 'Rating within range must appear.');
        $this->assertNotContains((int)$quizAfter->cmid, $cmids, 'Rating after end date must be excluded.');
    }

    /**
     * Empty datefrom string is accepted without error and applies no filter.
     *
     * MDL-UNIT-002
     */
    public function test_empty_datefrom_applies_no_filter(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $gen    = $this->getDataGenerator();
        $course = $gen->create_course();
        $quiz   = $gen->create_module('quiz', ['course' => $course->id]);

        $user = $gen->create_user();
        $this->insert_rating($quiz->cmid, $user->id, 1);

        // Empty string must not throw and must return data.
        $result = get_ratings_report::execute(0, 25, '', '', 0, '', '');
        $this->assertTrue($result['has_data']);
    }

    /**
     * Whitespace-only datefrom string is treated as empty and applies no filter.
     *
     * MDL-UNIT-002
     */
    public function test_whitespace_only_datefrom_applies_no_filter(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $gen    = $this->getDataGenerator();
        $course = $gen->create_course();
        $quiz   = $gen->create_module('quiz', ['course' => $course->id]);

        $user = $gen->create_user();
        $this->insert_rating($quiz->cmid, $user->id, 1);

        $result = get_ratings_report::execute(0, 25, '', '', 0, '   ', '');
        $this->assertTrue($result['has_data']);
    }

    /**
     * Day/month/year format (DD/MM/YYYY) is rejected with invalid_parameter_exception.
     *
     * MDL-UNIT-002
     */
    public function test_day_month_year_format_throws_invalid_parameter_exception(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $this->expectException(\invalid_parameter_exception::class);
        get_ratings_report::execute(0, 25, '', '', 0, '15/06/2025', '');
    }

    /**
     * Month-day-year format (MM-DD-YYYY) is rejected with invalid_parameter_exception.
     *
     * MDL-UNIT-002
     */
    public function test_month_day_year_format_throws_invalid_parameter_exception(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $this->expectException(\invalid_parameter_exception::class);
        get_ratings_report::execute(0, 25, '', '', 0, '06-15-2025', '');
    }

    /**
     * Date without zero-padding (YYYY-M-D) is rejected with invalid_parameter_exception.
     *
     * MDL-UNIT-002
     */
    public function test_date_without_zero_padding_throws_invalid_parameter_exception(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $this->expectException(\invalid_parameter_exception::class);
        get_ratings_report::execute(0, 25, '', '', 0, '2025-6-5', '');
    }

    /**
     * Arbitrary non-date text is rejected with invalid_parameter_exception.
     *
     * MDL-UNIT-002
     */
    public function test_arbitrary_text_as_date_throws_invalid_parameter_exception(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $this->expectException(\invalid_parameter_exception::class);
        get_ratings_report::execute(0, 25, '', '', 0, 'not-a-date', '');
    }

    /**
     * Month 13 is rejected with invalid_parameter_exception.
     *
     * The regex accepts the YYYY-MM-DD format, but strtotime() returns false
     * for month 13, causing the helper to throw.
     *
     * MDL-UNIT-002
     */
    public function test_month_13_throws_invalid_parameter_exception(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $this->expectException(\invalid_parameter_exception::class);
        get_ratings_report::execute(0, 25, '', '', 0, '2025-13-01', '');
    }

    /**
     * February 30 is accepted (PHP strtotime rolls it over silently into March).
     *
     * The method does not reject this because strtotime() does not return false;
     * it simply rolls the date forward. No exception must be thrown.
     *
     * MDL-UNIT-002
     */
    public function test_february_30_does_not_throw_due_to_silent_rollover(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Must not throw — PHP strtotime rolls over Feb 30 to a date in March.
        $result = get_ratings_report::execute(0, 25, '', '', 0, '2024-02-30', '');

        // The call succeeded; pagination structure must be present.
        $this->assertArrayHasKey('pagination', $result);
    }

    // -------------------------------------------------------------------------
    // MDL-UNIT-003 — Summary aggregation through execute()
    // -------------------------------------------------------------------------

    /**
     * Summary correctly aggregates total likes, dislikes, ratings, and activities across courses.
     *
     * MDL-UNIT-003
     */
    public function test_summary_aggregates_likes_dislikes_and_activities_correctly(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $gen = $this->getDataGenerator();

        // Course A: quizA1 gets 2 likes, quizA2 gets 1 dislike.
        $courseA = $gen->create_course();
        $quizA1  = $gen->create_module('quiz', ['course' => $courseA->id]);
        $quizA2  = $gen->create_module('quiz', ['course' => $courseA->id]);

        // Course B: quizB1 gets 3 likes, 2 dislikes.
        $courseB = $gen->create_course();
        $quizB1  = $gen->create_module('quiz', ['course' => $courseB->id]);

        $users = [];
        for ($i = 0; $i < 8; $i++) {
            $users[] = $gen->create_user();
        }

        // quizA1: 2 likes.
        $this->insert_rating($quizA1->cmid, $users[0]->id, 1);
        $this->insert_rating($quizA1->cmid, $users[1]->id, 1);

        // quizA2: 1 dislike.
        $this->insert_rating($quizA2->cmid, $users[2]->id, 0);

        // quizB1: 3 likes + 2 dislikes.
        $this->insert_rating($quizB1->cmid, $users[3]->id, 1);
        $this->insert_rating($quizB1->cmid, $users[4]->id, 1);
        $this->insert_rating($quizB1->cmid, $users[5]->id, 1);
        $this->insert_rating($quizB1->cmid, $users[6]->id, 0);
        $this->insert_rating($quizB1->cmid, $users[7]->id, 0);

        $result  = get_ratings_report::execute(0, 25, '', '', 0, '', '');
        $summary = $result['summary'];

        // 2 + 3 = 5 total likes.
        $this->assertEquals(5, $summary['total_likes']);
        // 1 + 2 = 3 total dislikes.
        $this->assertEquals(3, $summary['total_dislikes']);
        // 5 + 3 = 8 total ratings.
        $this->assertEquals(8, $summary['total_ratings']);
        // 2 courses.
        $this->assertEquals(2, $summary['total_courses']);
        // 3 activities total (quizA1, quizA2, quizB1).
        $this->assertEquals(3, $summary['total_activities']);
        // All 3 activities have at least one rating.
        $this->assertEquals(3, $summary['activities_with_ratings']);
    }

    /**
     * Summary overall_satisfaction is a string with one decimal place.
     *
     * 5 likes / 8 total = 62.5 % → formatted as '62.5'.
     *
     * MDL-UNIT-003
     */
    public function test_summary_overall_satisfaction_is_string_with_one_decimal(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $gen    = $this->getDataGenerator();
        $course = $gen->create_course();
        $quiz   = $gen->create_module('quiz', ['course' => $course->id]);

        // 5 likes + 3 dislikes = 62.5 % → '62.5'.
        $users = [];
        for ($i = 0; $i < 8; $i++) {
            $users[] = $gen->create_user();
        }
        for ($i = 0; $i < 5; $i++) {
            $this->insert_rating($quiz->cmid, $users[$i]->id, 1);
        }
        for ($i = 5; $i < 8; $i++) {
            $this->insert_rating($quiz->cmid, $users[$i]->id, 0);
        }

        $summary = get_ratings_report::execute(0, 25, '', '', 0, '', '')['summary'];

        $this->assertIsString($summary['overall_satisfaction']);
        $this->assertSame('62.5', $summary['overall_satisfaction']);
    }

    /**
     * Summary with 75% likes formats overall_satisfaction as '75.0'.
     *
     * MDL-UNIT-003
     */
    public function test_summary_satisfaction_formats_whole_percent_with_trailing_zero(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $gen    = $this->getDataGenerator();
        $course = $gen->create_course();
        $quiz   = $gen->create_module('quiz', ['course' => $course->id]);

        // 3 likes + 1 dislike = 75.0 % → '75.0'.
        $users = [];
        for ($i = 0; $i < 4; $i++) {
            $users[] = $gen->create_user();
        }
        $this->insert_rating($quiz->cmid, $users[0]->id, 1);
        $this->insert_rating($quiz->cmid, $users[1]->id, 1);
        $this->insert_rating($quiz->cmid, $users[2]->id, 1);
        $this->insert_rating($quiz->cmid, $users[3]->id, 0);

        $summary = get_ratings_report::execute(0, 25, '', '', 0, '', '')['summary'];

        $this->assertSame('75.0', $summary['overall_satisfaction']);
    }

    /**
     * Summary with zero ratings does not throw and reports '0.0' as overall_satisfaction.
     *
     * MDL-UNIT-003
     */
    public function test_summary_with_no_ratings_returns_zero_satisfaction_without_error(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Call with no data in the database at all.
        $result  = get_ratings_report::execute(0, 25, '', '', 0, '', '');
        $summary = $result['summary'];

        $this->assertEquals(0, $summary['total_ratings']);
        $this->assertSame('0.0', $summary['overall_satisfaction']);
        $this->assertEquals(0, $summary['total_courses']);
        $this->assertEquals(0, $summary['total_activities']);
        $this->assertEquals(0, $summary['activities_with_ratings']);
    }

    /**
     * Summary satisfaction_class is one of the four valid CSS class names.
     *
     * MDL-UNIT-003
     */
    public function test_summary_satisfaction_class_is_a_valid_class_name(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $gen    = $this->getDataGenerator();
        $course = $gen->create_course();
        $quiz   = $gen->create_module('quiz', ['course' => $course->id]);

        $user = $gen->create_user();
        $this->insert_rating($quiz->cmid, $user->id, 1);

        $summary = get_ratings_report::execute(0, 25, '', '', 0, '', '')['summary'];

        $this->assertContains(
            $summary['satisfaction_class'],
            ['success', 'warning', 'info', 'danger'],
            'satisfaction_class must be one of the four valid CSS class values.'
        );
    }

    // -------------------------------------------------------------------------
    // MDL-UNIT-004 — Keyword extraction through get_activity_comments::execute()
    // -------------------------------------------------------------------------

    /**
     * Create a role with viewcoursereport capability on a given module context.
     *
     * @param int $cmid
     * @param int $courseid
     * @return int $userid  ID of a newly created user who holds this role
     */
    private function create_user_with_viewcoursereport(int $cmid, int $courseid): int {
        $gen    = $this->getDataGenerator();
        $role   = $gen->create_role();
        assign_capability(
            'local/datacurso_ratings:viewcoursereport',
            CAP_ALLOW,
            $role,
            \context_module::instance($cmid)
        );
        $caller = $gen->create_user();
        $gen->enrol_user($caller->id, $courseid);
        role_assign($role, $caller->id, \context_module::instance($cmid));

        return $caller->id;
    }

    /**
     * The most frequent words appear in the keywords list, stop words are excluded,
     * and words of two characters or fewer are excluded.
     *
     * MDL-UNIT-004
     */
    public function test_keywords_returns_frequent_words_excluding_stopwords_and_short_words(): void {
        $this->resetAfterTest(true);

        $gen    = $this->getDataGenerator();
        $course = $gen->create_course();
        $quiz   = $gen->create_module('quiz', ['course' => $course->id]);

        $callerid = $this->create_user_with_viewcoursereport($quiz->cmid, $course->id);
        $this->setUser($callerid);

        // Each rating MUST have a unique feedback string because the plugin's
        // calculate_statistics() uses get_records_select with 'feedback' as the
        // record key. Duplicate feedback values are silently dropped by Moodle's
        // get_records, so we ensure uniqueness to get predictable counts.
        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();
        $u3 = $this->getDataGenerator()->create_user();

        $this->insert_rating($quiz->cmid, $u1->id, 1, 'curso curso contenido bueno');
        $this->insert_rating($quiz->cmid, $u2->id, 1, 'curso excelente contenido material');
        $this->insert_rating($quiz->cmid, $u3->id, 1, 'curso contenido de el la bueno');

        $result   = get_activity_comments::execute($quiz->cmid, 0, 20, '');
        $keywords = $result['statistics']['keywords'];

        $this->assertIsArray($keywords);
        $this->assertNotEmpty($keywords);

        // Each entry must have 'word' and 'frequency'.
        $this->assertArrayHasKey('word', $keywords[0]);
        $this->assertArrayHasKey('frequency', $keywords[0]);

        // 'curso' appears once per feedback × 3 feedbacks = 3.
        // 'contenido' appears once per feedback × 3 feedbacks = 3.
        $this->assertSame('curso', $keywords[0]['word']);
        $this->assertSame(3, $keywords[0]['frequency']);

        $words = array_column($keywords, 'word');
        $this->assertContains('contenido', $words);

        // Stop words must not appear.
        $words = array_column($keywords, 'word');
        $this->assertNotContains('de', $words, '"de" is a stop word and must be excluded.');
        $this->assertNotContains('el', $words, '"el" is a stop word and must be excluded.');
        $this->assertNotContains('la', $words, '"la" is a stop word and must be excluded.');
    }

    /**
     * Keywords are ordered by descending frequency and limited to 10 entries.
     *
     * MDL-UNIT-004
     */
    public function test_keywords_are_limited_to_ten_and_ordered_by_descending_frequency(): void {
        $this->resetAfterTest(true);

        $gen    = $this->getDataGenerator();
        $course = $gen->create_course();
        $quiz   = $gen->create_module('quiz', ['course' => $course->id]);

        $callerid = $this->create_user_with_viewcoursereport($quiz->cmid, $course->id);
        $this->setUser($callerid);

        // Build feedback text with 15 distinct words (letters only), each appearing a different number of times.
        // wordaa appears 15 times, wordbb appears 14 times, …, wordoo appears 1 time.
        // Using letter-only suffixes avoids digit-stripping by the keyword extractor regex.
        $parts = [];
        for ($i = 1; $i <= 15; $i++) {
            $suffix = str_repeat(chr(ord('a') + $i - 1), 2);
            $label  = 'word' . $suffix;
            $count  = 16 - $i;
            $parts[] = implode(' ', array_fill(0, $count, $label));
        }
        $feedback = implode(' ', $parts);

        $user = $this->getDataGenerator()->create_user();
        $this->insert_rating($quiz->cmid, $user->id, 1, $feedback);

        $result   = get_activity_comments::execute($quiz->cmid, 0, 20, '');
        $keywords = $result['statistics']['keywords'];

        // Must not exceed 10 entries.
        $this->assertLessThanOrEqual(10, count($keywords));

        // Must be ordered descending by frequency.
        for ($i = 1; $i < count($keywords); $i++) {
            $this->assertGreaterThanOrEqual(
                $keywords[$i]['frequency'],
                $keywords[$i - 1]['frequency'],
                "Keywords are not in descending frequency order at index {$i}."
            );
        }

        // The top entry must be 'wordaa' (15 occurrences, highest frequency).
        $this->assertSame('wordaa', $keywords[0]['word']);
    }

    /**
     * An activity with no ratings returns an empty keywords array without error.
     *
     * MDL-UNIT-004
     */
    public function test_keywords_returns_empty_array_when_activity_has_no_ratings(): void {
        $this->resetAfterTest(true);

        $gen    = $this->getDataGenerator();
        $course = $gen->create_course();
        $quiz   = $gen->create_module('quiz', ['course' => $course->id]);

        $callerid = $this->create_user_with_viewcoursereport($quiz->cmid, $course->id);
        $this->setUser($callerid);

        $result   = get_activity_comments::execute($quiz->cmid, 0, 20, '');
        $keywords = $result['statistics']['keywords'];

        $this->assertIsArray($keywords);
        $this->assertEmpty($keywords, 'Activity with no ratings must produce an empty keywords array.');
    }

    /**
     * An activity whose ratings have only empty-string feedback returns an empty keywords array.
     *
     * MDL-UNIT-004
     */
    public function test_keywords_returns_empty_array_when_all_feedback_is_empty(): void {
        $this->resetAfterTest(true);

        $gen    = $this->getDataGenerator();
        $course = $gen->create_course();
        $quiz   = $gen->create_module('quiz', ['course' => $course->id]);

        $callerid = $this->create_user_with_viewcoursereport($quiz->cmid, $course->id);
        $this->setUser($callerid);

        // Ratings with no feedback text — the statistics query filters them out (feedback != '').
        $u1 = $this->getDataGenerator()->create_user();
        $this->insert_rating($quiz->cmid, $u1->id, 1, '');

        $result   = get_activity_comments::execute($quiz->cmid, 0, 20, '');
        $keywords = $result['statistics']['keywords'];

        $this->assertIsArray($keywords);
        $this->assertEmpty($keywords, 'Ratings with empty feedback must yield an empty keywords array.');
    }

    /**
     * Words with Unicode accents and ñ survive the keyword extraction without being stripped.
     *
     * MDL-UNIT-004
     */
    public function test_keywords_preserves_unicode_accents_and_enie(): void {
        $this->resetAfterTest(true);

        $gen    = $this->getDataGenerator();
        $course = $gen->create_course();
        $quiz   = $gen->create_module('quiz', ['course' => $course->id]);

        $callerid = $this->create_user_with_viewcoursereport($quiz->cmid, $course->id);
        $this->setUser($callerid);

        // 'evaluación' × 3, 'enseñanza' × 2, 'diseño' × 4.
        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();
        $u3 = $this->getDataGenerator()->create_user();

        $this->insert_rating($quiz->cmid, $u1->id, 1, 'evaluación evaluación diseño diseño');
        $this->insert_rating($quiz->cmid, $u2->id, 1, 'evaluación enseñanza diseño diseño');
        $this->insert_rating($quiz->cmid, $u3->id, 1, 'enseñanza');

        $result   = get_activity_comments::execute($quiz->cmid, 0, 20, '');
        $keywords = $result['statistics']['keywords'];

        $this->assertNotEmpty($keywords);

        $words = array_column($keywords, 'word');

        $this->assertContains('evaluación', $words, '"evaluación" must be preserved as a keyword.');
        $this->assertContains('enseñanza', $words, '"enseñanza" must be preserved as a keyword.');
        $this->assertContains('diseño', $words, '"diseño" must be preserved as a keyword.');
    }
}
