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

namespace local_datacurso_ratings\task;

/**
 * Scheduled task to refresh recommendations cache.
 *
 * @package    local_datacurso_ratings
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_recommendations_cache extends \core\task\scheduled_task {
    /**
     * Task name shown in Moodle admin UI.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_updaterecommendationscache', 'local_datacurso_ratings');
    }

    /**
     * Execute the scheduled task.
     */
    public function execute() {
        global $DB;

        mtrace("Updating recommendations cache...");

        $cache = \cache::make('local_datacurso_ratings', 'recommendations');

        // Clear previous cache.
        $cache->purge();

        // Get all active users (optional: students only for optimization).
        $users = $DB->get_records_select('user', "deleted = 0 AND suspended = 0 AND confirmed = 1");

        $count = 0;
        foreach ($users as $user) {
            $userid = (int)$user->id;

            // Get recommendations.
            $recs = \local_datacurso_ratings\recommendations\service::get_recommendations_for_user($userid, 50);

            // Save to cache.
            $cachekey = "user_{$userid}";
            $cache->set($cachekey, $recs);

            $count++;
            if ($count % 50 === 0) {
                mtrace("Processed {$count} users...");
            }
        }

        mtrace("Finished updating cache for {$count} users.");
    }
}
