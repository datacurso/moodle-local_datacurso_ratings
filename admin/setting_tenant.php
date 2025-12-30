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
 * TODO describe file setting_tenant
 *
 * @package    local_datacurso_ratings
 * @copyright  2025 YOUR NAME <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

global $PAGE, $OUTPUT, $USER;

// Tenant resolution.
$tenantid = \tool_tenant\tenancy::get_tenant_id($USER->id);

$url = new moodle_url('/local/datacurso_ratings/admin/setting_tenant.php');
$PAGE->set_url(new moodle_url('/local/datacurso_ratings/admin/setting_tenant.php'));
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('settings', 'core'));
$PAGE->set_heading(get_string('pluginname', 'local_datacurso_ratings'));

// Form.
$form = new \local_datacurso_ratings\form\settings_tenant_form();

// Cancel.
if ($form->is_cancelled()) {
    redirect(
        new moodle_url('/admin/category.php', ['category' => 'localplugins'])
    );
}

// Submit.
if ($data = $form->get_data()) {
    \aiprovider_datacurso\local\tenant_config::save_from_form(
        'local_datacurso_ratings',
        $tenantid,
        $data
    );

    redirect(
        $url,
        get_string('changessaved'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Initial display.
$form->set_data_for_dynamic_submission();

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
