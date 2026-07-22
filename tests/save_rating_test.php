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
}
