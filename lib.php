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
 * Callback implementations for Datacurso Ratings.
 *
 * @package    local_datacurso_ratings
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Extiende la lista de informes del curso.
 *
 * @param navigation_node $navigation The navigation node.
 * @param stdClass $course The course object.
 * @param context_course $context The course context.
 */
function local_datacurso_ratings_extend_navigation_course($navigation, $course, $context) {
    // Teachers and managers can see the report.
    if (has_capability('local/datacurso_ratings:viewcoursereport', $context)) {
        // Search especific the nodo of "Reports".
        $reportsnode = $navigation->get('coursereports');
        if ($reportsnode) {
            $url = new moodle_url(
                '/local/datacurso_ratings/admin/report_ratings_course.php',
                ['id' => $course->id]
            );
            $reportsnode->add(
                get_string('activityratingsreport', 'local_datacurso_ratings'),
                $url,
                navigation_node::TYPE_SETTING,
                null,
                'local_datacurso_ratings_report'
            );
        }
    }
}
