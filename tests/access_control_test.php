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
 * Access control tests for AI analysis buttons and admin-only endpoints.
 *
 * @package    local_datacurso_ratings
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_datacurso_ratings;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/external_testcase.php');

use local_datacurso_ratings\external\get_ratings_report_course;
use local_datacurso_ratings\external\get_ratings_report;
use local_datacurso_ratings\external\feedback_service;

/**
 * Test suite for access control on AI buttons and admin-only services.
 *
 * @covers \local_datacurso_ratings\external\get_ratings_report_course
 * @covers \local_datacurso_ratings\external\get_ratings_report
 * @covers \local_datacurso_ratings\external\feedback_service
 * @covers \local_datacurso_ratings\external\get_activity_comments
 */
final class access_control_test extends \externallib_advanced_testcase {
    /**
     * Helper: assign a role with specific capabilities in a given context.
     *
     * @param int    $userid
     * @param int    $contextid
     * @param array  $caps  capability names to allow
     */
    private function assign_role_with_caps(int $userid, int $contextid, array $caps): void {
        global $DB;

        $roleid = create_role('testrole_' . uniqid(), 'testrole_' . uniqid(), '');
        foreach ($caps as $cap) {
            assign_capability($cap, CAP_ALLOW, $roleid, $contextid);
        }
        role_assign($roleid, $userid, $contextid);
    }

    /**
     * A user with viewcoursereport AND generateanalysiscourse receives
     * can_generate_course_ai = true in every activity row.
     *
     * MDL-INT-017 step 1
     */
    public function test_user_with_generate_capability_gets_ai_flag_true(): void {
        $this->resetAfterTest(true);

        $gen    = $this->getDataGenerator();
        $course = $gen->create_course();
        $gen->create_module('quiz', ['course' => $course->id]);

        $user    = $gen->create_user();
        $context = \context_course::instance($course->id);

        $this->assign_role_with_caps($user->id, $context->id, [
            'local/datacurso_ratings:viewcoursereport',
            'local/datacurso_ratings:generateanalysiscourse',
        ]);

        $gen->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);
        $result = get_ratings_report_course::execute($course->id);

        $this->assertNotEmpty($result, 'Result must not be empty — at least one CM expected.');
        foreach ($result as $row) {
            $this->assertTrue(
                $row['can_generate_course_ai'],
                'Expected can_generate_course_ai = true for user with generateanalysiscourse.'
            );
        }
    }

    /**
     * A user with viewcoursereport but WITHOUT generateanalysiscourse receives
     * can_generate_course_ai = false in every activity row.
     *
     * MDL-INT-017 step 2
     */
    public function test_user_without_generate_capability_gets_ai_flag_false(): void {
        $this->resetAfterTest(true);

        $gen    = $this->getDataGenerator();
        $course = $gen->create_course();
        $gen->create_module('quiz', ['course' => $course->id]);

        $user    = $gen->create_user();
        $context = \context_course::instance($course->id);

        // Grant viewcoursereport only — explicitly prohibit generateanalysiscourse.
        $roleid = create_role('testrole_nogen_' . uniqid(), 'testrole_nogen_' . uniqid(), '');
        assign_capability('local/datacurso_ratings:viewcoursereport', CAP_ALLOW, $roleid, $context->id);
        assign_capability('local/datacurso_ratings:generateanalysiscourse', CAP_PROHIBIT, $roleid, $context->id);
        role_assign($roleid, $user->id, $context->id);

        $gen->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);
        $result = get_ratings_report_course::execute($course->id);

        $this->assertNotEmpty($result, 'Result must not be empty — at least one CM expected.');
        foreach ($result as $row) {
            $this->assertFalse(
                $row['can_generate_course_ai'],
                'Expected can_generate_course_ai = false for user without generateanalysiscourse.'
            );
        }
    }

    /**
     * A user with viewcoursereport AND generateanalysisactivity receives
     * can_generate_activity_ai = true in the activity comments response.
     *
     * MDL-INT-017
     */
    public function test_user_with_activity_ai_capability_gets_flag_true(): void {
        $this->resetAfterTest(true);

        $gen    = $this->getDataGenerator();
        $course = $gen->create_course();
        $quiz   = $gen->create_module('quiz', ['course' => $course->id]);
        $cmctx  = \context_module::instance($quiz->cmid);

        $user = $gen->create_user();
        $gen->enrol_user($user->id, $course->id, 'student');
        $this->assign_role_with_caps($user->id, $cmctx->id, [
            'local/datacurso_ratings:viewcoursereport',
            'local/datacurso_ratings:generateanalysisactivity',
        ]);
        $this->setUser($user);

        $result = \local_datacurso_ratings\external\get_activity_comments::execute($quiz->cmid);

        $this->assertTrue(
            $result['can_generate_activity_ai'],
            'Expected can_generate_activity_ai = true for user with generateanalysisactivity.'
        );
    }

    /**
     * A user with viewcoursereport but WITHOUT generateanalysisactivity receives
     * can_generate_activity_ai = false in the activity comments response.
     *
     * MDL-INT-017
     */
    public function test_user_without_activity_ai_capability_gets_flag_false(): void {
        $this->resetAfterTest(true);

        $gen    = $this->getDataGenerator();
        $course = $gen->create_course();
        $quiz   = $gen->create_module('quiz', ['course' => $course->id]);
        $cmctx  = \context_module::instance($quiz->cmid);

        $user = $gen->create_user();
        $gen->enrol_user($user->id, $course->id, 'student');

        $roleid = create_role('testrole_noactai_' . uniqid(), 'testrole_noactai_' . uniqid(), '');
        assign_capability('local/datacurso_ratings:viewcoursereport', CAP_ALLOW, $roleid, $cmctx->id);
        assign_capability('local/datacurso_ratings:generateanalysisactivity', CAP_PROHIBIT, $roleid, $cmctx->id);
        role_assign($roleid, $user->id, $cmctx->id);
        $this->setUser($user);

        $result = \local_datacurso_ratings\external\get_activity_comments::execute($quiz->cmid);

        $this->assertFalse(
            $result['can_generate_activity_ai'],
            'Expected can_generate_activity_ai = false for user without generateanalysisactivity.'
        );
    }

    /**
     * A non-admin calling get_ratings_report::execute() receives a
     * required_capability_exception.
     *
     * MDL-INT-018
     */
    public function test_non_admin_cannot_access_general_ratings_report(): void {
        $this->resetAfterTest(true);

        $gen  = $this->getDataGenerator();
        $user = $gen->create_user();
        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        get_ratings_report::execute();
    }

    /**
     * A non-admin calling feedback_service::add_feedback() receives a
     * required_capability_exception.
     *
     * MDL-INT-018 step 2
     */
    public function test_non_admin_cannot_add_predefined_feedback_phrase(): void {
        $this->resetAfterTest(true);

        $gen  = $this->getDataGenerator();
        $user = $gen->create_user();
        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        feedback_service::add_feedback('Test phrase', 'like');
    }
}
