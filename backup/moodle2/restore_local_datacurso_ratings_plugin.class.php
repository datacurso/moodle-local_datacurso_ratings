<?php
// This file is part of Moodle - https://moodle.org/.
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
 * Restore plugin for local_datacurso_ratings.
 *
 * Handles the restoration of per-course plugin settings and per-activity
 * student ratings during course restore operations.
 *
 * @package    local_datacurso_ratings
 * @copyright  2025 Industria Elearning
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_local_datacurso_ratings_plugin extends restore_local_plugin {
    /**
     * Returns the definition of the course-level restore paths for this plugin.
     *
     * @return restore_path_element[]
     */
    protected function define_course_plugin_structure() {
        return [
            new restore_path_element(
                'datacurso_ratings_coursesetting',
                $this->get_pathfor('/coursesettings/coursesetting')
            ),
        ];
    }

    /**
     * Returns the definition of the module-level restore paths for this plugin.
     *
     * Ratings are restored at the module level so they are duplicated both when
     * copying a whole course and when duplicating a single activity.
     *
     * @return restore_path_element[]
     */
    protected function define_module_plugin_structure() {
        return [
            new restore_path_element(
                'datacurso_ratings_rating',
                $this->get_pathfor('/ratings/rating')
            ),
        ];
    }

    /**
     * Restores the per-course plugin configuration into the target course.
     *
     * The table has a unique index on courseid, so when the target course
     * already has a settings row it is updated instead of inserted.
     *
     * @param array $data Course settings data from the backup file.
     * @return void
     */
    public function process_datacurso_ratings_coursesetting($data) {
        global $DB;

        $data = (object) $data;
        $courseid = $this->task->get_courseid();
        $now = time();

        $existing = $DB->get_record('local_datacurso_ratings_course_settings', ['courseid' => $courseid]);
        if ($existing) {
            $existing->enabled = $data->enabled;
            $existing->timemodified = $now;
            $DB->update_record('local_datacurso_ratings_course_settings', $existing);
        } else {
            $record = new stdClass();
            $record->courseid = $courseid;
            $record->enabled = $data->enabled;
            $record->timecreated = !empty($data->timecreated) ? $data->timecreated : $now;
            $record->timemodified = !empty($data->timemodified) ? $data->timemodified : $now;
            $DB->insert_record('local_datacurso_ratings_course_settings', $record);
        }
    }

    /**
     * Restores a student rating for the just-restored course module.
     *
     * The user id is remapped through the restore mappings; if the user is not
     * part of the restore (user data excluded or anonymized), the rating is
     * skipped because it cannot be attributed to anyone.
     *
     * @param array $data Rating data from the backup file.
     * @return void
     */
    public function process_datacurso_ratings_rating($data) {
        global $DB;

        $data = (object) $data;

        // Remap the user: skip the rating when the user is not included in the restore.
        $userid = $this->get_mappingid('user', $data->userid);
        if (!$userid) {
            return;
        }

        // Target course, module and category.
        $courseid = $this->task->get_courseid();
        $cmid = $this->task->get_moduleid();
        $categoryid = $DB->get_field('course', 'category', ['id' => $courseid], MUST_EXIST);

        $record = new stdClass();
        $record->cmid = $cmid;
        $record->courseid = $courseid;
        $record->categoryid = $categoryid;
        $record->userid = $userid;
        $record->rating = $data->rating;
        $record->feedback = $data->feedback ?? null;
        $record->timecreated = !empty($data->timecreated) ? $data->timecreated : time();
        $record->timemodified = !empty($data->timemodified) ? $data->timemodified : time();

        // The table has a unique index on (cmid, userid): update when a row already exists.
        $existing = $DB->get_record('local_datacurso_ratings', ['cmid' => $cmid, 'userid' => $userid]);
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_datacurso_ratings', $record);
        } else {
            $DB->insert_record('local_datacurso_ratings', $record);
        }
    }
}
