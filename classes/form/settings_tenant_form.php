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

namespace local_datacurso_ratings\form;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

/**
 * Tenant-aware configuration form for Datacurso Ratings.
 *
 * This form replaces settings.php UI and will later persist data
 * to mdl_aiprovider_datacurso_tenant_config instead of mdl_config_plugins.
 *
 * @package    local_datacurso_ratings
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class settings_tenant_form extends \moodleform {
    /**
     * Form definition.
     */
    public function definition(): void {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('settings', 'core'));
        // Checkbox: enabled plugin default in all courses (tenant-aware).
        $mform->addElement(
            'advcheckbox',
            'enabled',
            get_string('enableplugin', 'local_datacurso_ratings'),
            get_string('enableplugin_desc', 'local_datacurso_ratings'),
            [],
            [0, 1]
        );

        $this->add_action_buttons(true);
    }

    /**
     * Initial data population (tenant-aware).
     */
    protected function get_initial_data(): \stdClass {
        global $USER;

        $data = new \stdClass();

        $tenantid = \tool_tenant\tenancy::get_tenant_id($USER->id);

        $data->enabled =
            (int) \aiprovider_datacurso\local\tenant_config::get(
                'local_datacurso_ratings',
                $tenantid,
                'enabled',
            );

        return $data;
    }

    /**
     * Required for dynamic submissions.
     */
    public function set_data_for_dynamic_submission(): void {
         $this->set_data($this->get_initial_data());
    }
}
