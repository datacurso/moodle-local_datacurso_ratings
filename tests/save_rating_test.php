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
 * Tests for the save_rating external function.
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
require_once($CFG->dirroot . '/local/datacurso_ratings/courselib.php');

/**
 * Integration tests for the save_rating external web service.
 *
 * @covers \local_datacurso_ratings\external\save_rating
 */
final class save_rating_test extends \externallib_advanced_testcase {
    /**
     * Provide the scenario fixture shared across test methods.
     *
     * Returns [course, quiz cm, student user].
     *
     * @return array
     */
    private function create_course_with_quiz_and_student(): array {
        $generator = $this->getDataGenerator();

        // The external function enforces the global switch server side.
        set_config('enabled', 1, 'local_datacurso_ratings');

        $category = $generator->create_category();
        $course   = $generator->create_course(['category' => $category->id]);
        $quiz     = $generator->create_module('quiz', ['course' => $course->id]);

        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id);

        return [$course, $quiz, $student];
    }

    /**
     * Verify that a valid like rating with optional feedback is persisted
     * with the correct course, category, and timestamp data.
     *
     * Spec: MDL-INT-002 step 1.
     */
    public function test_valid_like_rating_is_saved_with_correct_associated_data(): void {
        global $DB;
        $this->resetAfterTest(true);

        [$course, $quiz, $student] = $this->create_course_with_quiz_and_student();
        $this->setUser($student);

        $before = time();
        $result = \local_datacurso_ratings\external\save_rating::execute($quiz->cmid, 1, 'Great activity!');
        $after  = time();

        $this->assertTrue($result['status']);

        $record = $DB->get_record('local_datacurso_ratings', ['cmid' => $quiz->cmid, 'userid' => $student->id]);
        $this->assertNotFalse($record, 'Rating record must be created.');
        $this->assertEquals(1, $record->rating);
        $this->assertEquals('Great activity!', $record->feedback);
        $this->assertEquals($course->id, $record->courseid);
        $this->assertEquals($course->category, $record->categoryid);
        $this->assertGreaterThan(0, $record->timecreated, 'timecreated must be a positive timestamp.');
        $this->assertGreaterThanOrEqual($before, $record->timecreated);
        $this->assertLessThanOrEqual($after, $record->timecreated);
    }

    /**
     * Verify that a valid dislike rating (value 0) is accepted and persisted correctly.
     *
     * Spec: MDL-INT-002 step 1.
     */
    public function test_valid_dislike_rating_is_saved(): void {
        global $DB;
        $this->resetAfterTest(true);

        [$course, $quiz, $student] = $this->create_course_with_quiz_and_student();
        $this->setUser($student);

        $result = \local_datacurso_ratings\external\save_rating::execute($quiz->cmid, 0, 'Too hard.');

        $this->assertTrue($result['status']);

        $record = $DB->get_record('local_datacurso_ratings', ['cmid' => $quiz->cmid, 'userid' => $student->id]);
        $this->assertNotFalse($record);
        $this->assertEquals(0, $record->rating);
    }

    /**
     * Verify that rating values other than 0 and 1 are rejected with an exception.
     *
     * Spec: MDL-INT-002 step 2.
     */
    public function test_invalid_rating_value_throws_exception(): void {
        $this->resetAfterTest(true);

        [$course, $quiz, $student] = $this->create_course_with_quiz_and_student();
        $this->setUser($student);

        $this->expectException(\invalid_parameter_exception::class);
        \local_datacurso_ratings\external\save_rating::execute($quiz->cmid, 2);
    }

    /**
     * Verify that a second rating from the same user on the same activity updates
     * the existing record without creating a duplicate.
     *
     * Spec: MDL-INT-002 step 3.
     */
    public function test_second_rating_updates_existing_record_without_duplicate(): void {
        global $DB;
        $this->resetAfterTest(true);

        [$course, $quiz, $student] = $this->create_course_with_quiz_and_student();
        $this->setUser($student);

        // First rating: like.
        \local_datacurso_ratings\external\save_rating::execute($quiz->cmid, 1, 'Good');

        // Second rating: dislike.
        \local_datacurso_ratings\external\save_rating::execute($quiz->cmid, 0, 'Changed my mind');

        $count = $DB->count_records('local_datacurso_ratings', ['cmid' => $quiz->cmid, 'userid' => $student->id]);
        $this->assertEquals(1, $count, 'Only one rating record must exist per user per activity.');

        $record = $DB->get_record('local_datacurso_ratings', ['cmid' => $quiz->cmid, 'userid' => $student->id]);
        $this->assertEquals(0, $record->rating, 'Updated record must reflect the second rating value.');
        $this->assertEquals('Changed my mind', $record->feedback);
    }

    /**
     * Verify that feedback text exceeding 200 characters is truncated to the maximum allowed length.
     *
     * Spec: MDL-INT-002 step 4.
     */
    public function test_feedback_is_truncated_to_maximum_length(): void {
        global $DB;
        $this->resetAfterTest(true);

        [$course, $quiz, $student] = $this->create_course_with_quiz_and_student();
        $this->setUser($student);

        $longfeedback = str_repeat('a', 250);
        \local_datacurso_ratings\external\save_rating::execute($quiz->cmid, 1, $longfeedback);

        $record = $DB->get_record('local_datacurso_ratings', ['cmid' => $quiz->cmid, 'userid' => $student->id]);
        $this->assertNotFalse($record);
        $this->assertLessThanOrEqual(
            200,
            \core_text::strlen($record->feedback),
            'Feedback must be truncated to at most 200 characters.'
        );
    }

    /**
     * Verify that a predefined admin phrase longer than the configured comment limit
     * is stored in full: the limit only governs free-text student comments, while
     * predefined phrases are admin configuration with their own length validation.
     */
    public function test_predefined_phrase_is_not_truncated_by_comment_limit(): void {
        global $DB;
        $this->resetAfterTest(true);

        [$course, $quiz, $student] = $this->create_course_with_quiz_and_student();

        set_config('maxcommentlength', 10, 'local_datacurso_ratings');

        $phrase = 'The content was very clear and useful';
        $now = time();
        $DB->insert_record('local_datacurso_ratings_feedback', (object)[
            'feedbacktext' => $phrase,
            'type'         => 'like',
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);

        $this->setUser($student);
        \local_datacurso_ratings\external\save_rating::execute($quiz->cmid, 1, $phrase);

        $record = $DB->get_record('local_datacurso_ratings', ['cmid' => $quiz->cmid, 'userid' => $student->id]);
        $this->assertNotFalse($record);
        $this->assertSame(
            $phrase,
            $record->feedback,
            'A predefined admin phrase must be stored in full even when it exceeds maxcommentlength.'
        );
    }

    /**
     * A predefined phrase containing HTML-special characters must be exported raw to the
     * widget (Mustache escapes it once) so the value posted back matches the stored phrase
     * and is therefore exempt from the free-text limit.
     */
    public function test_predefined_phrase_with_special_characters_round_trips_unescaped(): void {
        global $DB, $OUTPUT;
        $this->resetAfterTest(true);

        [$course, $quiz, $student] = $this->create_course_with_quiz_and_student();

        set_config('maxcommentlength', 5, 'local_datacurso_ratings');

        $phrase = 'Clear & useful "notes"';
        $now = time();
        $DB->insert_record('local_datacurso_ratings_feedback', (object)[
            'feedbacktext' => $phrase,
            'type'         => 'like',
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);

        $page = new \local_datacurso_ratings\output\feedback_page('like');
        $data = $page->export_for_template($OUTPUT);
        $this->assertCount(1, $data['items']);
        $this->assertSame($phrase, $data['items'][0]['feedbacktext']);
        $this->assertArrayNotHasKey('sesskey', $data);

        $this->setUser($student);
        \local_datacurso_ratings\external\save_rating::execute($quiz->cmid, 1, $data['items'][0]['feedbacktext']);

        $record = $DB->get_record('local_datacurso_ratings', ['cmid' => $quiz->cmid, 'userid' => $student->id]);
        $this->assertNotFalse($record);
        $this->assertSame($phrase, $record->feedback);
    }

    /**
     * Verify that free-text feedback still honours a custom configured comment limit.
     */
    public function test_free_text_feedback_is_truncated_to_configured_limit(): void {
        global $DB;
        $this->resetAfterTest(true);

        [$course, $quiz, $student] = $this->create_course_with_quiz_and_student();

        set_config('maxcommentlength', 10, 'local_datacurso_ratings');

        $this->setUser($student);
        $freetext = 'This free-text comment is clearly longer than ten characters';
        \local_datacurso_ratings\external\save_rating::execute($quiz->cmid, 0, $freetext);

        $record = $DB->get_record('local_datacurso_ratings', ['cmid' => $quiz->cmid, 'userid' => $student->id]);
        $this->assertNotFalse($record);
        $this->assertSame(
            10,
            \core_text::strlen($record->feedback),
            'Free-text feedback must be truncated to the configured limit.'
        );
    }

    /**
     * Configured limits outside 1..2000 and their effective truncation length.
     *
     * @return array[]
     */
    public static function maxcommentlength_bounds_provider(): array {
        return [
            'zero falls back to default'     => ['configured' => 0, 'expected' => 200],
            'negative falls back to default' => ['configured' => -5, 'expected' => 200],
            'huge value is capped at 2000'   => ['configured' => 999999, 'expected' => 2000],
        ];
    }

    /**
     * Verify that the effective comment limit is bounded regardless of the stored setting.
     *
     * @dataProvider maxcommentlength_bounds_provider
     * @param int $configured Value stored in the maxcommentlength setting.
     * @param int $expected Effective truncation length.
     */
    public function test_maxcommentlength_setting_is_clamped(int $configured, int $expected): void {
        global $DB;
        $this->resetAfterTest(true);

        [$course, $quiz, $student] = $this->create_course_with_quiz_and_student();
        set_config('maxcommentlength', $configured, 'local_datacurso_ratings');

        $this->setUser($student);
        \local_datacurso_ratings\external\save_rating::execute($quiz->cmid, 1, str_repeat('a', 2500));

        $record = $DB->get_record('local_datacurso_ratings', ['cmid' => $quiz->cmid, 'userid' => $student->id]);
        $this->assertNotFalse($record);
        $this->assertSame($expected, \core_text::strlen($record->feedback));
    }

    /**
     * Call save_rating expecting a moodle_exception carrying the given error code.
     *
     * @param int $cmid Course module id to rate.
     * @param string $errorcode Expected moodle_exception::errorcode.
     */
    private function assert_execute_fails_with_errorcode(int $cmid, string $errorcode): void {
        try {
            \local_datacurso_ratings\external\save_rating::execute($cmid, 1, 'Feedback');
            $this->fail("Expected moodle_exception with errorcode '{$errorcode}'.");
        } catch (\moodle_exception $e) {
            $this->assertSame($errorcode, $e->errorcode);
        }
    }

    /**
     * Verify that a guest user cannot save a rating even when the course allows guest access.
     */
    public function test_guest_user_cannot_rate_even_with_guest_access_enabled(): void {
        global $DB;
        $this->resetAfterTest(true);

        [$course, $quiz, $student] = $this->create_course_with_quiz_and_student();

        // Enable guest access so require_login() lets the guest into the course.
        $guest    = enrol_get_plugin('guest');
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'guest']);
        if (!$instance) {
            $instanceid = $guest->add_default_instance($course);
            $instance   = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
        }
        $guest->update_status($instance, ENROL_INSTANCE_ENABLED);

        $this->setGuestUser();

        $this->expectException(\require_login_exception::class);
        \local_datacurso_ratings\external\save_rating::execute($quiz->cmid, 1);
    }

    /**
     * Verify that a logged-in user who is not enrolled in the course cannot save a rating.
     */
    public function test_user_not_enrolled_cannot_rate(): void {
        global $DB;
        $this->resetAfterTest(true);

        [$course, $quiz, $student] = $this->create_course_with_quiz_and_student();
        $outsider = $this->getDataGenerator()->create_user();
        $this->setUser($outsider);

        try {
            \local_datacurso_ratings\external\save_rating::execute($quiz->cmid, 1);
            $this->fail('Expected require_login_exception for a user who is not enrolled.');
        } catch (\require_login_exception $e) {
            $this->assertFalse(
                $DB->record_exists('local_datacurso_ratings', ['cmid' => $quiz->cmid, 'userid' => $outsider->id]),
                'No rating must be stored for a user who is not enrolled.'
            );
        }
    }

    /**
     * Verify that ratings are rejected server side when the plugin is globally disabled.
     */
    public function test_rating_rejected_when_plugin_globally_disabled(): void {
        global $DB;
        $this->resetAfterTest(true);

        [$course, $quiz, $student] = $this->create_course_with_quiz_and_student();
        set_config('enabled', 0, 'local_datacurso_ratings');
        $this->setUser($student);

        $this->assert_execute_fails_with_errorcode($quiz->cmid, 'ratingsdisabled');
        $this->assertFalse(
            $DB->record_exists('local_datacurso_ratings', ['cmid' => $quiz->cmid, 'userid' => $student->id]),
            'No rating must be stored while the plugin is globally disabled.'
        );
    }

    /**
     * Verify that ratings are rejected server side when the plugin is disabled for the course.
     */
    public function test_rating_rejected_when_course_disabled(): void {
        global $DB;
        $this->resetAfterTest(true);

        [$course, $quiz, $student] = $this->create_course_with_quiz_and_student();
        local_datacurso_ratings_set_course_enabled($course->id, false);
        $this->setUser($student);

        $this->assert_execute_fails_with_errorcode($quiz->cmid, 'ratingsdisabled');
        $this->assertFalse(
            $DB->record_exists('local_datacurso_ratings', ['cmid' => $quiz->cmid, 'userid' => $student->id]),
            'No rating must be stored while the plugin is disabled for the course.'
        );
    }

    /**
     * Verify that ratings are rejected for module types outside the supported list.
     */
    public function test_rating_rejected_for_unsupported_module_type(): void {
        global $DB;
        $this->resetAfterTest(true);

        [$course, $quiz, $student] = $this->create_course_with_quiz_and_student();
        $label = $this->getDataGenerator()->create_module('label', ['course' => $course->id]);
        $this->setUser($student);

        $this->assert_execute_fails_with_errorcode($label->cmid, 'unsupportedmodule');
        $this->assertFalse(
            $DB->record_exists('local_datacurso_ratings', ['cmid' => $label->cmid, 'userid' => $student->id]),
            'No rating must be stored for an unsupported module type.'
        );
    }

    /**
     * Verify that an enrolled user whose role lacks local/datacurso_ratings:rate cannot save a rating.
     */
    public function test_rating_rejected_without_rate_capability(): void {
        global $DB;
        $this->resetAfterTest(true);

        [$course, $quiz, $student] = $this->create_course_with_quiz_and_student();

        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        $context       = \context_module::instance($quiz->cmid);
        assign_capability('local/datacurso_ratings:rate', CAP_PROHIBIT, $studentroleid, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();

        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        \local_datacurso_ratings\external\save_rating::execute($quiz->cmid, 1);
    }

    /**
     * Verify that HTML tags in the feedback are stripped before the rating is stored.
     */
    public function test_html_tags_are_stripped_from_feedback(): void {
        global $DB;
        $this->resetAfterTest(true);

        [$course, $quiz, $student] = $this->create_course_with_quiz_and_student();
        $this->setUser($student);

        \local_datacurso_ratings\external\save_rating::execute($quiz->cmid, 1, '<script>alert(1)</script>Great');

        $record = $DB->get_record('local_datacurso_ratings', ['cmid' => $quiz->cmid, 'userid' => $student->id]);
        $this->assertNotFalse($record);
        $this->assertStringNotContainsString('<script', $record->feedback);
        $this->assertSame('alert(1)Great', $record->feedback);
    }
}
