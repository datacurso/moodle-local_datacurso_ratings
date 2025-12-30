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
use external_api;
use context_course;
use aiprovider_datacurso\httpclient\ai_services_api;

/**
 * Web service to get AI analysis for a whole course.
 *
 * @package    local_datacurso_ratings
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_ai_analysis_course extends external_api {
    /**
     * Input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
        ]);
    }

    /**
     * Execute main logic.
     *
     * @param int $courseid
     * @return array
     */
    public static function execute($courseid) {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
        ]);

        // Validate context.
        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $context = context_course::instance($course->id);
        self::validate_context($context);
        require_capability('local/datacurso_ratings:viewcoursereport', $context);

        // Gather ratings data for the course.
        $sql = "SELECT cm.id, cm.instance, cm.module, m.name AS modname, cm.idnumber, cm.section
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                 WHERE cm.course = :courseid";
        $cms = $DB->get_records_sql($sql, ['courseid' => $course->id]);

        $activities = [];
        $totalactivities = count($cms);
        $ratedactivities = 0;
        $likes = 0;
        $dislikes = 0;

        foreach ($cms as $cm) {
            $records = $DB->get_records('local_datacurso_ratings', [
                'cmid' => $cm->id,
            ]);

            if (empty($records)) {
                continue;
            }

            $ratedactivities++;
            $actlikes = 0;
            $actdislikes = 0;

            foreach ($records as $r) {
                if ((int)$r->rating === 1) {
                    $actlikes++;
                    $likes++;
                } else {
                    $actdislikes++;
                    $dislikes++;
                }
            }

            $total = $actlikes + $actdislikes;
            $approvalpercent = $total > 0 ? round(($actlikes / $total) * 100, 2) : 0.0;

            $activities[] = [
                'like' => $actlikes,
                'dislike' => $actdislikes,
                'approvalpercent' => $approvalpercent,
                'name' => $cm->modname . ' ' . $cm->id,
            ];
        }

        // Calculate overall approval percentage.
        $totalratings = $likes + $dislikes;
        $approvalpercent = $totalratings > 0 ? round(($likes / $totalratings) * 100, 2) : 0.0;

        // Body to send.
        $body = [
            'course' => $course->fullname,
            'total_activities' => $totalactivities,
            'rated_activities' => $ratedactivities,
            'approvalpercent' => $approvalpercent,
            'like' => $likes,
            'dislike' => $dislikes,
            'activities' => $activities,
        ];

        // Call AI API.
        $client = new ai_services_api();
        $response = $client->request('POST', 'rating/course', $body);

        // Extract IA response.
        $airesponse = '';
        if (is_array($response) && isset($response['reply'])) {
            $airesponse = $response['reply'];
        } else if (is_string($response)) {
            $airesponse = $response;
        }

        return [
            'courseid' => $params['courseid'],
            'ai_analysis_course' => $airesponse,
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'courseid' => new external_value(PARAM_INT, 'ID del curso'),
            'ai_analysis_course' => new external_value(PARAM_RAW, 'Summary generated by AI for the course'),
        ]);
    }
}
