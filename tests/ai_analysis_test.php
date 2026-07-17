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
 * Integration tests for the AI analysis external functions.
 *
 * @package    local_datacurso_ratings
 * @category   test
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_datacurso_ratings\external\get_ai_analysis_comments
 * @covers \local_datacurso_ratings\external\get_ai_analysis_course
 * @covers \local_datacurso_ratings\external\get_ai_analysis_global
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/external_testcase.php');

use aiprovider_datacurso\httpclient\ai_services_api;

// ---------------------------------------------------------------------------
// Testable subclasses — override get_ai_client() to inject a PHPUnit mock.
// ---------------------------------------------------------------------------

/**
 * Testable subclass of get_ai_analysis_comments.
 */
class testable_get_ai_analysis_comments extends \local_datacurso_ratings\external\get_ai_analysis_comments {
    /** @var ai_services_api|null Mock client injected by tests. */
    public static $mockclient = null;

    /**
     * Return the mock client instead of instantiating a real one.
     * @return ai_services_api
     */
    protected static function get_ai_client(): ai_services_api {
        return static::$mockclient;
    }
}

/**
 * Testable subclass of get_ai_analysis_course.
 */
class testable_get_ai_analysis_course extends \local_datacurso_ratings\external\get_ai_analysis_course {
    /** @var ai_services_api|null Mock client injected by tests. */
    public static $mockclient = null;

    /**
     * Return the mock client instead of instantiating a real one.
     * @return ai_services_api
     */
    protected static function get_ai_client(): ai_services_api {
        return static::$mockclient;
    }
}

/**
 * Testable subclass of get_ai_analysis_global.
 */
class testable_get_ai_analysis_global extends \local_datacurso_ratings\external\get_ai_analysis_global {
    /** @var ai_services_api|null Mock client injected by tests. */
    public static $mockclient = null;

    /**
     * Return the mock client instead of instantiating a real one.
     * @return ai_services_api
     */
    protected static function get_ai_client(): ai_services_api {
        return static::$mockclient;
    }
}

// ---------------------------------------------------------------------------
// Test class
// ---------------------------------------------------------------------------

/**
 * Integration tests for MDL-INT-008, MDL-INT-009 and MDL-INT-010.
 */
class ai_analysis_test extends \externallib_advanced_testcase {

    /**
     * Reset all static mock clients after each test to avoid cross-test pollution.
     */
    protected function tearDown(): void {
        testable_get_ai_analysis_comments::$mockclient = null;
        testable_get_ai_analysis_course::$mockclient = null;
        testable_get_ai_analysis_global::$mockclient = null;
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // MDL-INT-008 — AI analysis of activity comments
    // -----------------------------------------------------------------------

    /**
     * Verify that the AI analysis returns non-empty text when an activity has ratings.
     *
     * @spec MDL-INT-008 step 1
     */
    public function test_comments_analysis_returns_text_for_activity_with_ratings(): void {
        global $DB;
        $this->resetAfterTest(true);

        // Create course, module and user with the required capabilities.
        $course  = $this->getDataGenerator()->create_course();
        $page    = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $context = \context_module::instance($page->cmid);

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);
        $roleid = $this->assignUserCapability('local/datacurso_ratings:viewcoursereport', $context->id);
        $this->assignUserCapability('local/datacurso_ratings:generateanalysisactivity', $context->id, $roleid);

        // Insert a rating record.
        $rater = $this->getDataGenerator()->create_user();
        $DB->insert_record('local_datacurso_ratings', [
            'userid'      => $rater->id,
            'cmid'        => $page->cmid,
            'courseid'    => $course->id,
            'categoryid'  => 0,
            'rating'      => 1,
            'feedback'    => 'Great activity',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        // Mock the AI client to return a reply array.
        $mock = $this->createMock(ai_services_api::class);
        $mock->method('request')->willReturn(['reply' => 'AI analysis text for activity']);
        testable_get_ai_analysis_comments::$mockclient = $mock;

        $result = testable_get_ai_analysis_comments::execute($page->cmid);

        $this->assertNotEmpty($result['ai_analysis_comment'], 'Expected non-empty AI analysis text.');
    }

    /**
     * Verify that the approval percentage is calculated correctly and forwarded
     * to the AI service as part of the request payload.
     *
     * @spec MDL-INT-008 step 2
     */
    public function test_comments_analysis_sends_correct_approval_percent(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $page    = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $context = \context_module::instance($page->cmid);

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);
        $roleid = $this->assignUserCapability('local/datacurso_ratings:viewcoursereport', $context->id);
        $this->assignUserCapability('local/datacurso_ratings:generateanalysisactivity', $context->id, $roleid);

        // Insert 3 likes and 1 dislike → 75 % approval. Each needs a unique userid.
        foreach ([1, 1, 1, 0] as $rating) {
            $rater = $this->getDataGenerator()->create_user();
            $DB->insert_record('local_datacurso_ratings', [
                'userid'       => $rater->id,
                'cmid'         => $page->cmid,
                'courseid'     => $course->id,
                'categoryid'   => 0,
                'rating'       => $rating,
                'feedback'     => 'feedback ' . $rater->id,
                'timecreated'  => time(),
                'timemodified' => time(),
            ]);
        }

        // Capture what the AI client receives and verify approvalpercent.
        $mock = $this->createMock(ai_services_api::class);
        $mock->expects($this->once())
            ->method('request')
            ->with(
                $this->equalTo('POST'),
                $this->anything(),
                $this->callback(function ($body) {
                    return isset($body['approvalpercent']) && $body['approvalpercent'] === 75.0;
                })
            )
            ->willReturn(['reply' => 'analysis']);
        testable_get_ai_analysis_comments::$mockclient = $mock;

        testable_get_ai_analysis_comments::execute($page->cmid);
    }

    /**
     * Verify that a null response from the AI service (the only reachable non-array
     * branch, since ai_services_api::request() returns ?array) is handled gracefully
     * and produces an empty string rather than an error.
     *
     * @spec MDL-INT-008 step 3
     */
    public function test_comments_analysis_handles_null_ai_response(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $page    = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $context = \context_module::instance($page->cmid);

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);
        $roleid = $this->assignUserCapability('local/datacurso_ratings:viewcoursereport', $context->id);
        $this->assignUserCapability('local/datacurso_ratings:generateanalysisactivity', $context->id, $roleid);

        $rater = $this->getDataGenerator()->create_user();
        $DB->insert_record('local_datacurso_ratings', [
            'userid'       => $rater->id,
            'cmid'         => $page->cmid,
            'courseid'     => $course->id,
            'categoryid'   => 0,
            'rating'       => 1,
            'feedback'     => 'Good',
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);

        // AI service returns null (e.g. network or parsing failure handled upstream).
        $mock = $this->createMock(ai_services_api::class);
        $mock->method('request')->willReturn(null);
        testable_get_ai_analysis_comments::$mockclient = $mock;

        $result = testable_get_ai_analysis_comments::execute($page->cmid);

        $this->assertSame('', $result['ai_analysis_comment'],
            'Null AI response must produce an empty string, not an error.');
    }

    // -----------------------------------------------------------------------
    // MDL-INT-009 — AI analysis of course metrics
    // -----------------------------------------------------------------------

    /**
     * Verify that the course AI analysis returns non-empty text when the course
     * has at least one activity with ratings.
     *
     * @spec MDL-INT-009 step 1
     */
    public function test_course_analysis_returns_text_for_course_with_rated_activities(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $page    = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $context = \context_course::instance($course->id);

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);
        $roleid = $this->assignUserCapability('local/datacurso_ratings:viewcoursereport', $context->id);
        $this->assignUserCapability('local/datacurso_ratings:generateanalysiscourse', $context->id, $roleid);

        $rater = $this->getDataGenerator()->create_user();
        $DB->insert_record('local_datacurso_ratings', [
            'userid'       => $rater->id,
            'cmid'         => $page->cmid,
            'courseid'     => $course->id,
            'categoryid'   => 0,
            'rating'       => 1,
            'feedback'     => '',
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);

        $mock = $this->createMock(ai_services_api::class);
        $mock->method('request')->willReturn(['reply' => 'Course AI analysis text']);
        testable_get_ai_analysis_course::$mockclient = $mock;

        $result = testable_get_ai_analysis_course::execute($course->id);

        $this->assertNotEmpty($result['ai_analysis_course'], 'Expected non-empty AI analysis text for course.');
    }

    /**
     * Verify that activities without ratings are excluded from the payload sent
     * to the AI service, and that rated_activities count is accurate.
     *
     * @spec MDL-INT-009 step 2
     */
    public function test_course_analysis_excludes_unrated_activities_from_payload(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        // Create two activities — only one will have a rating.
        $rated   = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $unrated = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $context = \context_course::instance($course->id);

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);
        $roleid = $this->assignUserCapability('local/datacurso_ratings:viewcoursereport', $context->id);
        $this->assignUserCapability('local/datacurso_ratings:generateanalysiscourse', $context->id, $roleid);

        // Only the first activity gets a rating.
        $rater = $this->getDataGenerator()->create_user();
        $DB->insert_record('local_datacurso_ratings', [
            'userid'       => $rater->id,
            'cmid'         => $rated->cmid,
            'courseid'     => $course->id,
            'categoryid'   => 0,
            'rating'       => 1,
            'feedback'     => '',
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);

        $capturedbody = null;
        $mock = $this->createMock(ai_services_api::class);
        $mock->expects($this->once())
            ->method('request')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(function ($body) use (&$capturedbody) {
                    $capturedbody = $body;
                    return true;
                })
            )
            ->willReturn(['reply' => 'analysis']);
        testable_get_ai_analysis_course::$mockclient = $mock;

        testable_get_ai_analysis_course::execute($course->id);

        $this->assertNotNull($capturedbody, 'Expected AI client to be called.');
        $this->assertSame(1, $capturedbody['rated_activities'],
            'Only the one rated activity should be counted in rated_activities.');
        $this->assertCount(1, $capturedbody['activities'],
            'Unrated activity must be excluded from the activities array sent to the AI.');
    }

    // -----------------------------------------------------------------------
    // MDL-INT-010 — Global platform AI analysis
    // -----------------------------------------------------------------------

    /**
     * Verify that the global AI analysis returns text when rating data exists
     * in the platform.
     *
     * @spec MDL-INT-010 step 1
     */
    public function test_global_analysis_returns_text_when_data_exists(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $page   = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $user   = $this->getDataGenerator()->create_user();

        $DB->insert_record('local_datacurso_ratings', [
            'userid'       => $user->id,
            'cmid'         => $page->cmid,
            'courseid'     => $course->id,
            'categoryid'   => 0,
            'rating'       => 1,
            'feedback'     => '',
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);

        // Grant moodle/site:config so validate_context passes.
        $syscontext = \context_system::instance();
        $this->setAdminUser();

        $mock = $this->createMock(ai_services_api::class);
        $mock->method('request')->willReturn(['reply' => 'Global AI analysis text']);
        testable_get_ai_analysis_global::$mockclient = $mock;

        $result = testable_get_ai_analysis_global::execute();

        $this->assertNotEmpty($result['analysis'], 'Expected non-empty global AI analysis text.');
    }

    /**
     * Verify that the global AI analysis does not produce a division-by-zero error
     * when there are no ratings in the platform.
     *
     * @spec MDL-INT-010 step 2
     */
    public function test_global_analysis_handles_zero_ratings_without_division_by_zero(): void {
        global $DB;
        $this->resetAfterTest(true);

        // Ensure the ratings table is empty for a clean slate.
        $DB->delete_records('local_datacurso_ratings');

        $this->setAdminUser();

        $capturedbody = null;
        $mock = $this->createMock(ai_services_api::class);
        $mock->expects($this->once())
            ->method('request')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(function ($body) use (&$capturedbody) {
                    $capturedbody = $body;
                    return true;
                })
            )
            ->willReturn(['reply' => '']);
        testable_get_ai_analysis_global::$mockclient = $mock;

        // Must not throw any exception.
        $result = testable_get_ai_analysis_global::execute();

        $this->assertNotNull($capturedbody, 'Expected AI client to be called.');
        $this->assertSame(0, $capturedbody['approvalpercent'],
            'approvalpercent must be 0 when there are no ratings, not a division-by-zero error.');
        $this->assertSame(0, $capturedbody['like'],
            'like count must be 0 when there are no ratings.');
        $this->assertSame(0, $capturedbody['dislike'],
            'dislike count must be 0 when there are no ratings.');
    }
}
