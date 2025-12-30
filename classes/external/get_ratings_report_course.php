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
 * External service to get course-level ratings report.
 *
 * @package    local_datacurso_ratings
 * @category   external
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_datacurso_ratings\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;
use external_api;
use context_course;
use moodle_exception;
use core\context;

/**
 * Web service to get course report of activities with ratings.
 *
 * @package    local_datacurso_ratings
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_ratings_report_course extends external_api {
    /**
     * Define parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_REQUIRED),
        ]);
    }

    /**
     * Main logic: returns activities with their ratings for a course.
     *
     * @param int $courseid
     * @return array
     * @throws moodle_exception
     */
    public static function execute($courseid) {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid]);

        // Validate context.
        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/datacurso_ratings:viewcoursereport', $context);

        // Ensure course exists.
        if (!$DB->record_exists('course', ['id' => $params['courseid']])) {
            throw new moodle_exception('invalidcourseid', 'error');
        }

        // Get all course modules.
        $modinfo = get_fast_modinfo($params['courseid']);
        $cms = $modinfo->get_cms();

        $result = [];

        // Detect DB type for comment aggregation.
        $dbfamily = $DB->get_dbfamily();
        $concatcomments = ($dbfamily === 'postgres')
            ? "STRING_AGG(DISTINCT r.feedback, ' / ')"
            : "GROUP_CONCAT(DISTINCT r.feedback SEPARATOR ' / ')";

        foreach ($cms as $cm) {
            if (!$cm->uservisible) {
                continue; // Skip hidden or restricted modules.
            }

            // Aggregate likes/dislikes and comments for each module.
            $sql = "
                SELECT
                    COALESCE(SUM(CASE WHEN r.rating = 1 THEN 1 ELSE 0 END), 0) AS likes,
                    COALESCE(SUM(CASE WHEN r.rating = 0 THEN 1 ELSE 0 END), 0) AS dislikes,
                    COALESCE(
                        ROUND(SUM(CASE WHEN r.rating = 1 THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(r.id), 0), 1),
                        0
                    ) AS approvalpercent,
                    $concatcomments AS comments
                FROM {local_datacurso_ratings} r
                WHERE r.cmid = :cmid
            ";

            $record = $DB->get_record_sql($sql, ['cmid' => $cm->id]);

            $commentsarray = [];
            if (!empty($record->comments)) {
                $commentsarray = preg_split('/\s*\/\s*/', trim($record->comments));
            }

            $result[] = [
                'course' => $modinfo->get_course()->fullname,
                'categoryid' => $modinfo->get_course()->category,
                'activity' => $cm->name,
                'modname' => $cm->modname,
                'cmid' => (int)$cm->id,
                'url' => $cm->url ? $cm->url->out(false) : '',
                'likes' => (int)$record->likes,
                'dislikes' => (int)$record->dislikes,
                'approvalpercent' => (float)$record->approvalpercent,
                'comments' => $commentsarray,
            ];
        }

        return $result;
    }

    /**
     * Define return structure.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'course' => new external_value(PARAM_TEXT, 'Course name'),
                'categoryid' => new external_value(PARAM_INT, 'Category ID'),
                'activity' => new external_value(PARAM_TEXT, 'Activity name'),
                'modname' => new external_value(PARAM_TEXT, 'Module type'),
                'cmid' => new external_value(PARAM_INT, 'Course module ID'),
                'url' => new external_value(PARAM_URL, 'Activity URL', VALUE_OPTIONAL),
                'likes' => new external_value(PARAM_INT, 'Number of likes'),
                'dislikes' => new external_value(PARAM_INT, 'Number of dislikes'),
                'approvalpercent' => new external_value(PARAM_FLOAT, 'Approval percentage'),
                'comments' => new external_multiple_structure(
                    new external_value(PARAM_RAW, 'Individual comment'),
                    'List of comments',
                    VALUE_OPTIONAL
                ),
            ])
        );
    }
}
