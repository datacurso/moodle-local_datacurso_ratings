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
 * Character limit tests for predefined feedback phrases.
 *
 * @package    local_datacurso_ratings
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_datacurso_ratings;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/external_testcase.php');

use local_datacurso_ratings\external\feedback_service;

/**
 * Test suite for the 150-character limit on predefined feedback phrases.
 *
 * NOTE: As of the current implementation feedback_service::add_feedback() does
 * NOT enforce the 150-character limit — it delegates truncation to PARAM_TEXT
 * cleaning and DB column width only.  The second test (151-char phrase) is
 * therefore written to DOCUMENT the expected behavior.  If the service stores
 * the phrase without rejection or truncation that test will FAIL, which is
 * intentional: the failure reveals a missing validation feature (MDL-INT-020).
 *
 * @covers \local_datacurso_ratings\external\feedback_service
 */
final class feedback_phrase_limits_test extends \externallib_advanced_testcase {
    /** Maximum allowed phrase length defined by the spec. */
    const MAX_PHRASE_LENGTH = 150;

    /**
     * Set up: ensure we run as admin so capability checks pass.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();
    }

    /**
     * A phrase exactly at the 150-character limit is saved successfully.
     *
     * MDL-INT-020 step 1
     */
    public function test_phrase_at_max_length_is_accepted(): void {
        global $DB;

        $phrase = str_repeat('a', self::MAX_PHRASE_LENGTH);
        $this->assertSame(self::MAX_PHRASE_LENGTH, strlen($phrase));

        $result = feedback_service::add_feedback($phrase, 'like');

        $this->assertArrayHasKey('id', $result);
        $this->assertGreaterThan(0, $result['id'], 'add_feedback must return a positive integer ID.');

        $record = $DB->get_record('local_datacurso_ratings_feedback', ['id' => $result['id']]);
        $this->assertNotFalse($record, 'The phrase record must exist in the database.');

        // The stored text must not be longer than the limit.
        $this->assertLessThanOrEqual(
            self::MAX_PHRASE_LENGTH,
            strlen($record->feedbacktext),
            'Stored phrase must not exceed the maximum length of ' . self::MAX_PHRASE_LENGTH . ' characters.'
        );
    }

    /**
     * A phrase one character over the limit (151 chars) is rejected or truncated.
     *
     * This test documents the EXPECTED behavior.  If the service currently stores
     * the full 151-char phrase without enforcement, the assertion will FAIL — that
     * failure is correct and reveals the missing validation (MDL-INT-020).
     *
     * MDL-INT-020 step 2
     */
    public function test_phrase_exceeding_max_length_is_rejected_or_truncated(): void {
        global $DB;

        $phrase = str_repeat('b', self::MAX_PHRASE_LENGTH + 1);
        $this->assertSame(self::MAX_PHRASE_LENGTH + 1, strlen($phrase));

        $threwexception = false;
        $id = null;

        try {
            $result = feedback_service::add_feedback($phrase, 'dislike');
            $id = $result['id'] ?? null;
        } catch (\invalid_parameter_exception $e) {
            $threwexception = true;
        } catch (\moodle_exception $e) {
            $threwexception = true;
        }

        if ($threwexception) {
            // Rejection path: the service correctly refused the over-limit phrase.
            $this->assertTrue(true, 'Service correctly rejected phrase exceeding the limit.');
            return;
        }

        // Truncation path: the service saved something — verify the stored text was truncated.
        $this->assertNotNull($id, 'If no exception, an ID must be returned.');
        $record = $DB->get_record('local_datacurso_ratings_feedback', ['id' => $id]);
        $this->assertNotFalse($record, 'Record must exist when service does not throw.');

        $this->assertLessThanOrEqual(
            self::MAX_PHRASE_LENGTH,
            strlen($record->feedbacktext),
            'Stored phrase must not exceed ' . self::MAX_PHRASE_LENGTH .
            ' characters — either reject or truncate is required (MDL-INT-020).'
        );
    }
}
