<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * External functions and services declaration for local_datacurso_ratings.
 *
 * @package     local_datacurso_ratings
 * @category    external
 * @copyright   2025 Industria Elearning <info@industriaelearning.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_datacurso_ratings_save_rating' => [
        'classname'   => 'local_datacurso_ratings\external\save_rating',
        'methodname'  => 'execute',
        'classpath'   => '',
        'description' => 'Saves a user rating (like/dislike) and optional feedback for a course module.',
        'type'        => 'write',
        'ajax'        => true,
    ],
    'local_datacurso_ratings_add_feedback' => [
        'classname'   => 'local_datacurso_ratings\external\feedback_service',
        'methodname'  => 'add_feedback',
        'classpath'   => '',
        'description' => 'Adds a feedback entry.',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'moodle/site:config',
    ],
    'local_datacurso_ratings_delete_feedback' => [
        'classname'   => 'local_datacurso_ratings\external\feedback_service',
        'methodname'  => 'delete_feedback',
        'classpath'   => '',
        'description' => 'Deletes a feedback entry.',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'moodle/site:config',
    ],
    'local_datacurso_ratings_get_ratings_report' => [
        'classname'   => 'local_datacurso_ratings\external\get_ratings_report',
        'methodname'  => 'execute',
        'description' => 'Returns a summarized report of ratings and feedback per course or activity.',
        'type'        => 'read',
        'ajax'        => true,
    ],
    'local_datacurso_ratings_get_ratings_report_course' => [
        'classname'   => 'local_datacurso_ratings\external\get_ratings_report_course',
        'methodname'  => 'execute',
        'classpath'   => '',
        'description' => 'Returns a rating report for all activities within a specific course.',
        'type'        => 'read',
        'ajax'        => true,
    ],
    'local_datacurso_ratings_get_activity_comments' => [
        'classname'   => 'local_datacurso_ratings\external\get_activity_comments',
        'methodname'  => 'execute',
        'classpath'   => '',
        'description' => 'Retrieves all comments for a given activity by cmid.',
        'type'        => 'read',
        'ajax'        => true,
    ],
    'local_datacurso_ratings_get_courses_by_category' => [
        'classname'   => 'local_datacurso_ratings\external\get_courses_by_category',
        'methodname'  => 'execute',
        'classpath'   => '',
        'description' => 'Retrieves all courses belonging to a given category.',
        'type'        => 'read',
        'ajax'        => true,
    ],
    'local_datacurso_ratings_get_ai_analysis_comments' => [
        'classname'   => 'local_datacurso_ratings\external\get_ai_analysis_comments',
        'methodname'  => 'execute',
        'classpath'   => '',
        'description' => 'Returns an AI-generated analysis of comments from a specific activity.',
        'type'        => 'read',
        'ajax'        => true,
    ],
    'local_datacurso_ratings_get_ai_analysis_course' => [
        'classname'   => 'local_datacurso_ratings\external\get_ai_analysis_course',
        'methodname'  => 'execute',
        'classpath'   => '',
        'description' => 'Returns a general AI-generated analysis for an entire course.',
        'type'        => 'read',
        'ajax'        => true,
    ],
    'local_datacurso_ratings_get_ai_analysis_global' => [
        'classname'   => 'local_datacurso_ratings\external\get_ai_analysis_global',
        'methodname'  => 'execute',
        'classpath'   => '',
        'description' => 'Returns a global AI-generated analysis across all activities in a course.',
        'type'        => 'read',
        'ajax'        => true,
    ],
];
