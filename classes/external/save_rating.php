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

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use context_module;
use invalid_parameter_exception;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

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
            'feedback' => new external_value(PARAM_RAW, 'Optional feedback for negative rating', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Save a rating for the given course module.
     *
     * @param int $cmid Course module id
     * @param int $rating Rating value (0 or 1)
     * @param string $feedback Optional feedback
     * @return array Status of the operation
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

        $tenantid = \tool_tenant\tenancy::get_tenant_id($USER->id);

        $data = (object)[
            'cmid' => $cm->id,
            'userid' => $USER->id,
            'courseid' => $courseid,
            'categoryid' => $categoryid,
            'rating' => $r,
            'feedback' => (string)$params['feedback'],
            'tenant_id' => $tenantid,
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
