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
 * Feedback admin page.
 *
 * @package    local_datacurso_ratings
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url(new moodle_url('/local/datacurso_ratings/admin/feedback.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('managefeedback', 'local_datacurso_ratings'));
$PAGE->set_heading(get_string('managefeedback', 'local_datacurso_ratings'));


$tab = optional_param('tab', 'responselike', PARAM_ALPHA);

$tabs = [];
$tabs[] = new tabobject(
    'responselike',
    new moodle_url('/local/datacurso_ratings/admin/feedback.php', ['tab' => 'responselike']),
    get_string('managefeedbacklike', 'local_datacurso_ratings')
);

$tabs[] = new tabobject(
    'responsedislike',
    new moodle_url('/local/datacurso_ratings/admin/feedback.php', ['tab' => 'responsedislike']),
    get_string('managefeedbackdislike', 'local_datacurso_ratings')
);

echo $OUTPUT->header();
echo $OUTPUT->tabtree($tabs, $tab);

global $USER;

// Tenant resolution.
$tenantid = \tool_tenant\tenancy::get_tenant_id($USER->id);

switch ($tab) {
    case 'responselike':
        $renderable = new \local_datacurso_ratings\output\feedback_page('like', $tenantid);
        echo $OUTPUT->render($renderable);
        break;

    case 'responsedislike':
        $renderable = new \local_datacurso_ratings\output\feedback_page('dislike', $tenantid);
        echo $OUTPUT->render($renderable);
        break;
}

echo $OUTPUT->footer();
