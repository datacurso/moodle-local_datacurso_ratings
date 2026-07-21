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
 * Tests for get_ratings_report_course external function.
 *
 * @package    local_datacurso_ratings
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_datacurso_ratings\external\get_ratings_report_course
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/external_testcase.php');

use local_datacurso_ratings\external\get_ratings_report_course;

/**
 * Test suite for the get_ratings_report_course web service.
 */
class get_ratings_report_course_test extends externallib_advanced_testcase {

    /**
     * Insert a rating record directly into the plugin table.
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
     * Report shows only visible activities; hidden ones are excluded.
     *
     * MDL-INT-005 step 1
     */
    public function test_report_shows_only_visible_activities(): void {
        $this->resetAfterTest(true);

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();

        // Create a user with the viewcoursereport capability (not admin — admin sees hidden CMs).
        $role = $gen->create_role();
        assign_capability('local/datacurso_ratings:viewcoursereport', CAP_ALLOW,
            $role, context_course::instance($course->id));

        $user = $gen->create_user();
        $gen->enrol_user($user->id, $course->id);
        role_assign($role, $user->id, context_course::instance($course->id));
        $this->setUser($user);

        // Visible quiz.
        $visibleQuiz = $gen->create_module('quiz', ['course' => $course->id, 'visible' => 1]);
        // Hidden quiz.
        $hiddenQuiz  = $gen->create_module('quiz', ['course' => $course->id, 'visible' => 0]);

        $result = get_ratings_report_course::execute((int)$course->id);

        $cmids = array_column($result, 'cmid');
        $this->assertContains((int)$visibleQuiz->cmid, $cmids);
        $this->assertNotContains((int)$hiddenQuiz->cmid, $cmids);
    }

    /**
     * Each activity in the report carries its likes, dislikes, and approval percentage.
     *
     * MDL-INT-005 step 2
     */
    public function test_each_activity_shows_correct_rating_metrics(): void {
        $this->resetAfterTest(true);

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();

        $role = $gen->create_role();
        assign_capability('local/datacurso_ratings:viewcoursereport', CAP_ALLOW,
            $role, context_course::instance($course->id));

        $user = $gen->create_user();
        $gen->enrol_user($user->id, $course->id);
        role_assign($role, $user->id, context_course::instance($course->id));
        $this->setUser($user);

        $quiz = $gen->create_module('quiz', ['course' => $course->id, 'name' => 'MetricsQuiz']);

        $rater1 = $gen->create_user();
        $rater2 = $gen->create_user();
        $rater3 = $gen->create_user();

        // 2 likes, 1 dislike.
        $this->insert_rating($quiz->cmid, $rater1->id, 1);
        $this->insert_rating($quiz->cmid, $rater2->id, 1);
        $this->insert_rating($quiz->cmid, $rater3->id, 0);

        $result = get_ratings_report_course::execute((int)$course->id);

        // Find the activity entry for our quiz.
        $entry = null;
        foreach ($result as $row) {
            if ($row['cmid'] === (int)$quiz->cmid) {
                $entry = $row;
                break;
            }
        }

        $this->assertNotNull($entry, 'Activity entry not found in result');
        $this->assertEquals(2, $entry['likes']);
        $this->assertEquals(1, $entry['dislikes']);
        // 2 likes out of 3 total = 66.7 %.
        $this->assertEqualsWithDelta(66.7, $entry['approvalpercent'], 0.1);
    }

    /**
     * Requesting a non-existent course ID raises a moodle_exception.
     *
     * MDL-INT-005 step 3
     */
    public function test_nonexistent_course_raises_exception(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $this->expectException(\moodle_exception::class);
        get_ratings_report_course::execute(999999);
    }
}
