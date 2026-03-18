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
 * Hook callbacks for Datacurso Ratings course form.
 *
 * @package    local_datacurso_ratings
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_datacurso_ratings\hook;

use core_course\hook\after_form_definition;
use core_course\hook\after_form_definition_after_data;
use core_course\hook\after_form_submission;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../courselib.php');

/**
 * Hook callback to add elements to the course edit form.
 */
class course_form_hook {

    /**
     * Add elements to the course edit form.
     *
     * @param after_form_definition $hook
     * @return void
     */
    public static function after_form_definition(after_form_definition $hook): void {
        global $COURSE;

        if (!get_config('local_datacurso_ratings', 'enabled')) {
            return;
        }

        $mform = $hook->mform;

        $mform->addElement('header', 'local_datacurso_ratings_header', get_string('pluginname', 'local_datacurso_ratings'));

        $enabled = local_datacurso_ratings_get_course_enabled($COURSE->id);
        $defaultvalue = ($enabled === null) ? 1 : ($enabled ? 1 : 0);

        $mform->addElement('selectyesno', 'local_datacurso_ratings_enabled', get_string('enableforcourse', 'local_datacurso_ratings'));
        $mform->setDefault('local_datacurso_ratings_enabled', $defaultvalue);
        $mform->addHelpButton('local_datacurso_ratings_enabled', 'enableforcourse', 'local_datacurso_ratings');
    }

    /**
     * Required for compatibility (after definition + data).
     *
     * @param after_form_definition_after_data $hook
     * @return void
     */
    public static function after_form_definition_after_data(after_form_definition_after_data $hook): void {
    }

    /**
     * Save course settings after form submission.
     *
     * @param after_form_submission $hook
     * @return void
     */
    public static function after_form_submission(after_form_submission $hook): void {
        global $COURSE;

        if (!get_config('local_datacurso_ratings', 'enabled')) {
            return;
        }

        $data = $hook->get_data();
        
        $courseid = isset($data->id) ? $data->id : ($COURSE->id ?? 0);
        
        if (!$courseid || !isset($data->local_datacurso_ratings_enabled)) {
            return;
        }

        local_datacurso_ratings_set_course_enabled($courseid, (bool)$data->local_datacurso_ratings_enabled);
    }
}
