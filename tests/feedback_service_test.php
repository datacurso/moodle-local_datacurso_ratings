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
 * Tests for the feedback_service external function.
 *
 * @package    local_datacurso_ratings
 * @category   test
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_datacurso_ratings\external\feedback_service
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/external_testcase.php');

/**
 * Integration tests for the feedback_service external web service (predefined phrases CRUD).
 */
class feedback_service_test extends \externallib_advanced_testcase {

    /**
     * Verify that a predefined phrase with a given type (like/dislike) can be added
     * and that the returned id is a positive integer referencing the persisted record.
     *
     * @spec MDL-INT-003 step 1
     */
    public function test_add_predefined_phrase_returns_positive_id_and_persists_record(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $result = \local_datacurso_ratings\external\feedback_service::add_feedback(
            'Excellent explanation',
            'like'
        );

        $this->assertArrayHasKey('id', $result);
        $this->assertGreaterThan(0, $result['id'], 'Returned id must be a positive integer.');

        $record = $DB->get_record('local_datacurso_ratings_feedback', ['id' => $result['id']]);
        $this->assertNotFalse($record, 'Record must exist in the database.');
        $this->assertEquals('Excellent explanation', $record->feedbacktext);
        $this->assertEquals('like', $record->type);
    }

    /**
     * Verify that a predefined phrase with type "dislike" can be added and persisted.
     *
     * @spec MDL-INT-003 step 1
     */
    public function test_add_predefined_phrase_with_dislike_type_persists_correctly(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $result = \local_datacurso_ratings\external\feedback_service::add_feedback(
            'Too difficult',
            'dislike'
        );

        $this->assertGreaterThan(0, $result['id']);

        $record = $DB->get_record('local_datacurso_ratings_feedback', ['id' => $result['id']]);
        $this->assertNotFalse($record);
        $this->assertEquals('dislike', $record->type);
    }

    /**
     * Verify that an existing predefined phrase can be deleted by id and that
     * the record is removed from the database.
     *
     * @spec MDL-INT-003 step 2
     */
    public function test_delete_existing_predefined_phrase_removes_record(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Create the phrase first.
        $add_result = \local_datacurso_ratings\external\feedback_service::add_feedback(
            'Very clear content',
            'like'
        );
        $phrase_id = $add_result['id'];

        // Verify it exists.
        $this->assertNotFalse($DB->get_record('local_datacurso_ratings_feedback', ['id' => $phrase_id]));

        // Delete it.
        $delete_result = \local_datacurso_ratings\external\feedback_service::delete_feedback($phrase_id);

        $this->assertArrayHasKey('message', $delete_result);

        // Verify it no longer exists.
        $record = $DB->get_record('local_datacurso_ratings_feedback', ['id' => $phrase_id]);
        $this->assertFalse($record, 'Record must not exist in the database after deletion.');
    }

    /**
     * Verify that deleting a phrase does not affect other phrases in the same table.
     *
     * @spec MDL-INT-003 step 2
     */
    public function test_delete_one_phrase_does_not_affect_other_phrases(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $result_a = \local_datacurso_ratings\external\feedback_service::add_feedback('Phrase A', 'like');
        $result_b = \local_datacurso_ratings\external\feedback_service::add_feedback('Phrase B', 'like');

        // Delete only phrase A.
        \local_datacurso_ratings\external\feedback_service::delete_feedback($result_a['id']);

        $this->assertFalse(
            $DB->get_record('local_datacurso_ratings_feedback', ['id' => $result_a['id']]),
            'Phrase A must be deleted.'
        );
        $this->assertNotFalse(
            $DB->get_record('local_datacurso_ratings_feedback', ['id' => $result_b['id']]),
            'Phrase B must remain untouched.'
        );
    }
}
