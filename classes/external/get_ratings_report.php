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

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");

use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;
use external_api;
use context_system;

/**
 * Web service to get general report of activities with ratings.
 *
 * @package    local_datacurso_ratings
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_ratings_report extends external_api {
    /**
     * Function input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([]);
    }

    /**
     * Main logic: queries and returns the information.
     *
     * @return array
     */
    public static function execute() {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), []);

        // Validate context.
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:config', $context);

        // Detect DB type for compatibility.
        $dbfamily = $DB->get_dbfamily();

        // Handle GROUP_CONCAT / STRING_AGG depending on DB type.
        if ($dbfamily === 'postgres') {
            $concatcomments = "STRING_AGG(DISTINCT r.feedback, ' / ') AS comentarios";
        } else {
            $concatcomments = "GROUP_CONCAT(DISTINCT r.feedback SEPARATOR ' / ') AS comentarios";
        }

        $tenantid = \tool_tenant\tenancy::get_tenant_id($USER->id);

        // Main SQL query (compatible with both MySQL and PostgreSQL).
         $sql = "
           SELECT r.cmid,
                   cm.course,
                   SUM(CASE WHEN r.rating = 1 THEN 1 ELSE 0 END) AS likes,
                   SUM(CASE WHEN r.rating = 0 THEN 1 ELSE 0 END) AS dislikes,
                   ROUND(SUM(CASE WHEN r.rating = 1 THEN 1 ELSE 0 END) * 100.0
                   / NULLIF(COUNT(r.id), 0), 1) AS porcentaje_aprobacion,
                   {$concatcomments}
            FROM {local_datacurso_ratings} r
            JOIN {course_modules} cm ON cm.id = r.cmid
            WHERE r.tenant_id = :tenantid
            GROUP BY r.cmid, cm.course
            ORDER BY cm.course ASC
        ";

        $params = [
            'tenantid' => $tenantid,
        ];

        $records = $DB->get_records_sql($sql, $params);
        $result = [];

        // Cache to reuse modinfo per course.
        $coursecache = [];

        foreach ($records as $r) {
            $courseid = $r->course;

            if (!isset($coursecache[$courseid])) {
                $coursecache[$courseid] = get_fast_modinfo($courseid);
            }

            $modinfo = $coursecache[$courseid];

            // Validate module existence.
            if (!$modinfo->cms || !isset($modinfo->cms[$r->cmid])) {
                continue;
            }

            $cm = $modinfo->get_cm($r->cmid);
            if (!$cm->uservisible) {
                continue;
            }

            // Prepare comments array.
            $commentsarray = [];
            if (!empty($r->comentarios)) {
                $commentsarray = explode(' / ', $r->comentarios);
            }

            $result[] = [
                'course' => $modinfo->get_course()->fullname,
                'categoryid' => $modinfo->get_course()->category,
                'activity' => $cm->name,
                'modname' => $cm->modname,
                'cmid' => $cm->id,
                'url' => $cm->url ? $cm->url->out(false) : '',
                'likes' => (int)$r->likes,
                'dislikes' => (int)$r->dislikes,
                'approvalpercent' => (float)$r->porcentaje_aprobacion,
                'comments' => $commentsarray,
            ];
        }

        return $result;
    }

    /**
     * Output structure.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'course' => new external_value(PARAM_TEXT, 'Course name'),
                'categoryid' => new external_value(PARAM_INT, 'Course category ID'),
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
