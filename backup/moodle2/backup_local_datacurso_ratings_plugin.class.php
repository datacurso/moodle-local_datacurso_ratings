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
 * Backup plugin for local_datacurso_ratings.
 *
 * @package    local_datacurso_ratings
 * @category   backup
 * @copyright  2025 Industria Elearning
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_local_datacurso_ratings_plugin extends backup_local_plugin {
    /**
     * Define the course-level structure to include in the backup.
     *
     * The per-course plugin configuration is course-scoped (keyed by courseid),
     * so it is backed up at the course level.
     *
     * @return backup_plugin_element
     */
    protected function define_course_plugin_structure() {
        $plugin = $this->get_plugin_element(null);
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());
        $plugin->add_child($pluginwrapper);

        // Container for the per-course plugin settings.
        $settings = new backup_nested_element('coursesettings');
        $pluginwrapper->add_child($settings);

        // Per-course plugin configuration record.
        $setting = new backup_nested_element('coursesetting', ['id'], [
            'courseid',
            'enabled',
            'timecreated',
            'timemodified',
        ]);
        $settings->add_child($setting);

        // Capture the configuration for this course.
        $setting->set_source_table('local_datacurso_ratings_course_settings', ['courseid' => backup::VAR_COURSEID]);

        // Map dependent entities.
        $setting->annotate_ids('course', 'courseid');

        return $plugin;
    }

    /**
     * Define the per-activity structure to include in the backup.
     *
     * Ratings are activity-scoped (keyed by the course module id), so they are
     * backed up at the module level. This way they travel with the activity in
     * both whole-course copies and single-activity duplications.
     *
     * @return backup_plugin_element
     */
    protected function define_module_plugin_structure() {
        $plugin = $this->get_plugin_element(null);
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());
        $plugin->add_child($pluginwrapper);

        // Container for the ratings of this activity.
        $ratings = new backup_nested_element('ratings');
        $pluginwrapper->add_child($ratings);

        // Each student rating for this activity.
        $rating = new backup_nested_element('rating', ['id'], [
            'cmid',
            'courseid',
            'categoryid',
            'userid',
            'rating',
            'feedback',
            'timecreated',
            'timemodified',
        ]);
        $ratings->add_child($rating);

        // Ratings are per-user data: only include them when the backup carries user information.
        if ($this->get_setting_value('userinfo')) {
            $rating->set_source_table('local_datacurso_ratings', ['cmid' => backup::VAR_MODID]);
        }

        // Map dependent entities.
        $rating->annotate_ids('user', 'userid');
        $rating->annotate_ids('course', 'courseid');

        return $plugin;
    }
}
