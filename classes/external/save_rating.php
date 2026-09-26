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

namespace local_datacurso_ratings\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use context_module;
use invalid_parameter_exception;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../courselib.php');

/**
 * External function to save a rating for a course module.
 *
 * @package    local_datacurso_ratings
 * @category   external
 * @copyright  2025 Developer <developer@datacurso.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_rating extends external_api {
    /**
     * Define the expected parameters for the service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'rating' => new external_value(PARAM_INT, 'Rating: 1 = like, 0 = dislike'),
            'feedback' => new external_value(
                PARAM_RAW,
                'Optional feedback text (HTML is stripped server side)',
                VALUE_DEFAULT,
                ''
            ),
        ]);
    }

    /**
     * Save a rating for the given course module.
     *
     * @param int $cmid Course module id
     * @param int $rating Rating value (0 or 1)
     * @param string $feedback Optional feedback (HTML tags are stripped server side)
     * @return array Status of the operation
     * @throws \require_login_exception If the current user is a guest or cannot access the module
     * @throws \required_capability_exception If the user lacks local/datacurso_ratings:rate
     * @throws \moodle_exception If ratings are disabled or the module type is not supported
     * @throws invalid_parameter_exception If rating value is invalid
     */
    public static function execute(int $cmid, int $rating, string $feedback = ''): array {
        global $DB, $USER;

        // Validate params.
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'rating' => $rating,
            'feedback' => $feedback,
        ]);

        // Ensure CM exists and user can access.
        $cm = get_coursemodule_from_id(null, $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        // Guests may reach the module page but must never persist ratings.
        if (isguestuser()) {
            throw new \require_login_exception('Guests cannot rate');
        }
        require_capability('local/datacurso_ratings:rate', $context);

        // Enforce the global and course-level switches server side, not only in the widget.
        if (!local_datacurso_ratings_is_enabled_for_course((int)$cm->course)) {
            throw new \moodle_exception('ratingsdisabled', 'local_datacurso_ratings');
        }
        if (!local_datacurso_ratings_is_module_supported($cm->modname)) {
            throw new \moodle_exception('unsupportedmodule', 'local_datacurso_ratings');
        }

        $r = (int)$params['rating'];
        if ($r !== 0 && $r !== 1) {
            throw new invalid_parameter_exception('Invalid rating value. Must be 0 or 1.');
        }

        // Get course and category.
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $courseid = (int)$course->id;
        $categoryid = (int)$course->category;

        $now = time();
        $record = $DB->get_record(
            'local_datacurso_ratings',
            ['cmid' => $cm->id, 'userid' => $USER->id]
        );

        // The parameter is declared PARAM_RAW because validate_parameters() rejects values
        // that change after cleaning; HTML is stripped here instead so plain text is stored.
        $feedback = trim((string)$params['feedback']);
        $feedback = trim(clean_param($feedback, PARAM_TEXT));

        // The comment limit only governs free-text student input: predefined admin
        // phrases have their own length validation and must be stored in full.
        if ($feedback !== '' && !self::is_predefined_phrase($feedback)) {
            $feedback = \core_text::substr($feedback, 0, local_datacurso_ratings_get_max_comment_length());
        }

        $data = (object)[
            'cmid' => $cm->id,
            'userid' => $USER->id,
            'courseid' => $courseid,
            'categoryid' => $categoryid,
            'rating' => $r,
            'feedback' => $feedback,
            'timemodified' => $now,
        ];

        if ($record) {
            $data->id = $record->id;
            $DB->update_record('local_datacurso_ratings', $data);
        } else {
            $data->timecreated = $now;
            $DB->insert_record('local_datacurso_ratings', $data);
        }

        return ['status' => true];
    }

    /**
     * Check whether the given feedback text matches a predefined admin phrase.
     *
     * @param string $feedback Feedback text as received from the client
     * @return bool
     */
    private static function is_predefined_phrase(string $feedback): bool {
        global $DB;

        $compare = $DB->sql_compare_text('feedbacktext', 255) . ' = ' . $DB->sql_compare_text(':feedbacktext', 255);
        return $DB->record_exists_select('local_datacurso_ratings_feedback', $compare, ['feedbacktext' => $feedback]);
    }

    /**
     * Define the return structure of the service.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'Operation status'),
        ]);
    }
}
