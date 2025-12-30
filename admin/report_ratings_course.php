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
 * Course ratings report page
 *
 * @package    local_datacurso_ratings
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');

// Get course ID parameter.
$courseid = required_param('id', PARAM_INT);

// Verify course exists.
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

// Security checks.
require_login($course);
$context = context_course::instance($course->id);
require_capability('local/datacurso_ratings:viewcoursereport', $context);

// Set up page.
$PAGE->set_url('/local/datacurso_ratings/admin/report_ratings_course.php', ['id' => $courseid]);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('activityratingsreport', 'local_datacurso_ratings'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('activityratingsreport', 'local_datacurso_ratings'));

echo $OUTPUT->header();
$headerlogo = new \local_datacurso_ratings\output\header_logo();
echo $OUTPUT->render($headerlogo);

// Container for the report (will be populated by JavaScript).
echo '<div id="ratings-report-container"></div>';

// Initialize the JavaScript module.
$PAGE->requires->js_call_amd('local_datacurso_ratings/ratings_report_course', 'init', [$courseid]);
$PAGE->requires->js_call_amd('local_datacurso_ratings/comments_modal', 'init');
$PAGE->requires->js_call_amd('local_datacurso_ratings/get_ai_analysis_comments', 'init');
$PAGE->requires->js_call_amd('local_datacurso_ratings/get_ai_analysis_course', 'init', [$courseid]);

echo $OUTPUT->footer();
