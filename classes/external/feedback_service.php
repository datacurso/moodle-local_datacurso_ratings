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
use context_system;

/**
 * Class feedback_service
 *
 * External services for managing feedback in the plugin.
 *
 * @package    local_datacurso_ratings
 * @copyright  2025 Industria Elearning <info@industriaelearning.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedback_service extends external_api {
    /**
     * Define input parameters for add_feedback.
     *
     * @return external_function_parameters
     */
    public static function add_feedback_parameters() {
        return new external_function_parameters([
            'feedbacktext' => new external_value(PARAM_TEXT, 'Feedback text', VALUE_REQUIRED),
            'type' => new external_value(PARAM_ALPHA, 'Feedback type (like or dislike)', VALUE_REQUIRED),
        ]);
    }

    /**
     * Add a feedback.
     *
     * @param string $feedbacktext Feedback text
     * @param string $type Feedback type (like or dislike)
     * @return array Array with:
     *  - int id: ID of the created feedback
     *  - string message: Confirmation message
     */
    public static function add_feedback($feedbacktext, $type) {
        global $DB, $USER;

        self::validate_context(context_system::instance());
        $params = self::validate_parameters(
            self::add_feedback_parameters(),
            ['feedbacktext' => $feedbacktext, 'type' => $type]
        );
        require_capability('moodle/site:config', context_system::instance());

        // Tenant resolution.
        $tenantid = \tool_tenant\tenancy::get_tenant_id($USER->id);

        $record = (object)[
            'feedbacktext' => $params['feedbacktext'],
            'type' => $params['type'],
            'tenant_id' => $tenantid,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $id = $DB->insert_record('local_datacurso_ratings_feedback', $record);
        return ['id' => $id, 'message' => get_string('feedbacksaved', 'local_datacurso_ratings')];
    }

    /**
     * Define return structure for add_feedback.
     *
     * @return external_single_structure
     */
    public static function add_feedback_returns() {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'ID of the created feedback'),
            'message' => new external_value(PARAM_TEXT, 'Confirmation message'),
        ]);
    }

    /**
     * Define input parameters for delete_feedback.
     *
     * @return external_function_parameters
     */
    public static function delete_feedback_parameters() {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'ID of the feedback to delete', VALUE_REQUIRED),
        ]);
    }

    /**
     * Delete a feedback.
     *
     * @param int $id ID of the feedback to delete
     * @return array Array with:
     *  - string message: Confirmation message
     */
    public static function delete_feedback($id) {
        global $DB;

        self::validate_context(context_system::instance());
        $params = self::validate_parameters(self::delete_feedback_parameters(), ['id' => $id]);
        require_capability('moodle/site:config', context_system::instance());

        $DB->delete_records('local_datacurso_ratings_feedback', ['id' => $params['id']]);

        return ['message' => get_string('feedbackdeleted', 'local_datacurso_ratings')];
    }

    /**
     * Define return structure for delete_feedback.
     *
     * @return external_single_structure
     */
    public static function delete_feedback_returns() {
        return new external_single_structure([
            'message' => new external_value(PARAM_TEXT, 'Confirmation message'),
        ]);
    }
}
