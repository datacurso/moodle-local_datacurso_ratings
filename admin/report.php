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
 * General ratings report page
 *
 * @package    local_datacurso_ratings
 * @copyright  2025 Industria Elearning <info@industriaelearning.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_url(new moodle_url('/local/datacurso_ratings/admin/report.php'));
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('ratingsreport', 'local_datacurso_ratings'));

// Get categories.
$categorieslist = core_course_category::make_categories_list();
$categories = [];
foreach ($categorieslist as $id => $name) {
    $categories[] = [
        'id' => $id,
        'name' => $name,
        'isdefault' => ($id == 1),
    ];
}

echo $OUTPUT->header();
$headerlogo = new \local_datacurso_ratings\output\header_logo();
echo $OUTPUT->render($headerlogo);

echo '<div id="general-ratings-report-container"
          data-categories="' . htmlentities(json_encode($categories)) . '">
      </div>';

$PAGE->requires->js_call_amd('local_datacurso_ratings/ratings_report', 'init', []);
$PAGE->requires->js_call_amd('local_datacurso_ratings/comments_modal', 'init');
$PAGE->requires->js_call_amd('local_datacurso_ratings/get_ai_analysis_comments', 'init');
$PAGE->requires->js_call_amd('local_datacurso_ratings/get_ai_analysis_global', 'init');

echo $OUTPUT->footer();
