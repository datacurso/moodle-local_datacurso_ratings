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
 * Web service to get courses by category.
 *
 * @package    local_datacurso_ratings
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_courses_by_category extends external_api {
    /**
     * Function input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'categoryid' => new external_value(PARAM_INT, 'Category ID to filter courses'),
        ]);
    }

    /**
     * Main logic: gets courses from a specific category.
     *
     * @param int $categoryid
     * @return array
     */
    public static function execute($categoryid) {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'categoryid' => $categoryid,
        ]);

        // Validate system context.
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:config', $context);

        // Verify that the category exists.
        $category = $DB->get_record('course_categories', ['id' => $params['categoryid']], '*', MUST_EXIST);

        // Get courses from the category.
        $courses = $DB->get_records('course', [
            'category' => $params['categoryid'],
            'visible' => 1,
        ], 'fullname ASC', 'id, fullname, shortname, category');

        // Process results.
        $result = [];
        foreach ($courses as $course) {
            // Skip the site course (ID = 1).
            if ($course->id == SITEID) {
                continue;
            }

            // Check if the user has access to the course.
            $coursecontext = \context_course::instance($course->id);
            if (
                has_capability('moodle/course:view', $coursecontext) ||
                has_capability('local/datacurso_ratings:viewcoursereport', $coursecontext)
            ) {
                $result[] = [
                    'id' => $course->id,
                    'fullname' => $course->fullname,
                    'shortname' => $course->shortname,
                    'categoryid' => $course->category,
                ];
            }
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
                'id' => new external_value(PARAM_INT, 'Course ID'),
                'fullname' => new external_value(PARAM_TEXT, 'Course full name'),
                'shortname' => new external_value(PARAM_TEXT, 'Course short name'),
                'categoryid' => new external_value(PARAM_INT, 'Category ID'),
            ])
        );
    }
}
