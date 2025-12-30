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
 * Plugin administration pages are defined here.
 *
 * @package     local_datacurso_ratings
 * @category    admin
 * @copyright   Josue <josue@datacurso.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_category(
        'local_datacurso_ratings_category',
        get_string('pluginname', 'local_datacurso_ratings')
    ));

    // Link feedback page.
    $ADMIN->add('local_datacurso_ratings_category', new admin_externalpage(
        'local_datacurso_ratings_feedback',
        get_string('managefeedback', 'local_datacurso_ratings'),
        new moodle_url('/local/datacurso_ratings/admin/feedback.php'),
        'moodle/site:config'
    ));

    // Link report general ratings.
    $ADMIN->add('local_datacurso_ratings_category', new admin_externalpage(
        'local_datacurso_ratings_report',
        get_string('ratingsreport', 'local_datacurso_ratings'),
        new moodle_url('/local/datacurso_ratings/admin/report.php'),
        'moodle/site:config'
    ));

    // New page of general settings.
    $ADMIN->add('local_datacurso_ratings_category', new admin_externalpage(
        'local_datacurso_ratings_settings',
        get_string('generalsettings', 'local_datacurso_ratings'),
        new moodle_url('/local/datacurso_ratings/admin/setting_tenant.php'),
        'moodle/site:config'
    ));

}
