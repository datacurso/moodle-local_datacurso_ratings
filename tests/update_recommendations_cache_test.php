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
 * Tests for the update_recommendations_cache scheduled task.
 *
 * @package    local_datacurso_ratings
 * @category   test
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_datacurso_ratings;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/external_testcase.php');

/**
 * Tests for the scheduled task that rebuilds the recommendations cache (INT-014).
 *
 * @covers \local_datacurso_ratings\task\update_recommendations_cache
 */
final class update_recommendations_cache_test extends \externallib_advanced_testcase {
    /**
     * Verify that the task recalculates recommendations for every active user
     * and stores an entry in the cache for each one.
     *
     * Spec: MDL-INT-014 step 1.
     */
    public function test_task_rebuilds_cache_for_all_active_users(): void {
        global $DB;
        $this->resetAfterTest(true);

        // Create active users in addition to the default admin.
        $user1 = $this->getDataGenerator()->create_user(['deleted' => 0, 'suspended' => 0, 'confirmed' => 1]);
        $user2 = $this->getDataGenerator()->create_user(['deleted' => 0, 'suspended' => 0, 'confirmed' => 1]);

        // Ensure cache is empty before execution.
        $cache = \cache::make('local_datacurso_ratings', 'recommendations');
        $cache->purge();

        // Execute the task. It reports progress via mtrace, so declare the expected output
        // to avoid a risky-test warning.
        $this->expectOutputRegex('/Finished updating cache/');
        $task = new \local_datacurso_ratings\task\update_recommendations_cache();
        $task->execute();

        // Retrieve all active users the way the task does.
        $activeusers = $DB->get_records_select('user', "deleted = 0 AND suspended = 0 AND confirmed = 1");

        foreach ($activeusers as $user) {
            $cachekey = "user_{$user->id}";
            $cached   = $cache->get($cachekey);
            $this->assertNotFalse(
                $cached,
                "Cache entry must exist for active user ID {$user->id} after task execution."
            );
        }
    }

    /**
     * Verify that each user receives at most the maximum configured number of recommendations (50).
     *
     * Spec: MDL-INT-014 step 2.
     */
    public function test_each_user_receives_at_most_maximum_recommendations(): void {
        global $DB;
        $this->resetAfterTest(true);

        $user  = $this->getDataGenerator()->create_user(['deleted' => 0, 'suspended' => 0, 'confirmed' => 1]);
        $cache = \cache::make('local_datacurso_ratings', 'recommendations');
        $cache->purge();

        $this->expectOutputRegex('/Finished updating cache/');
        $task = new \local_datacurso_ratings\task\update_recommendations_cache();
        $task->execute();

        $cachekey = "user_{$user->id}";
        $cached   = $cache->get($cachekey);

        // Cached value must be an array (possibly empty, but never more than 50 items).
        $this->assertIsArray($cached, 'Cached recommendations must be an array.');
        $this->assertLessThanOrEqual(
            50,
            count($cached),
            'Each user must receive at most 50 recommendations (the task-configured maximum).'
        );
    }

    /**
     * The task must cache exactly what the service returns for each user; it only
     * precomputes the global ratio once instead of per user, without altering results.
     */
    public function test_cached_recommendations_match_direct_service_call(): void {
        global $DB;
        $this->resetAfterTest(true);

        $gen      = $this->getDataGenerator();
        $category = $gen->create_category();
        $user     = $gen->create_user(['deleted' => 0, 'suspended' => 0, 'confirmed' => 1]);

        // Ratings from other users so the global ratio passes the 80% threshold and
        // an unenrolled course becomes a recommendation.
        $ratedcourse = $gen->create_course(['category' => $category->id, 'visible' => 1]);
        for ($i = 0; $i < 5; $i++) {
            $rater = $gen->create_user();
            $page  = $gen->create_module('page', ['course' => $ratedcourse->id]);
            $DB->insert_record('local_datacurso_ratings', (object)[
                'userid'       => $rater->id,
                'cmid'         => $page->cmid,
                'courseid'     => $ratedcourse->id,
                'categoryid'   => $category->id,
                'rating'       => 1,
                'feedback'     => '',
                'timecreated'  => time(),
                'timemodified' => time(),
            ]);
        }
        $gen->create_course(['category' => $category->id, 'visible' => 1]);

        $cache = \cache::make('local_datacurso_ratings', 'recommendations');
        $cache->purge();

        $this->expectOutputRegex('/Finished updating cache/');
        $task = new \local_datacurso_ratings\task\update_recommendations_cache();
        $task->execute();

        $cached = $cache->get("user_{$user->id}");
        $this->assertNotEmpty($cached);

        $direct = \local_datacurso_ratings\recommendations\service::get_recommendations_for_user($user->id, 50);
        $this->assertSame($direct, $cached);
    }

    /**
     * Deleted, suspended and unconfirmed users are skipped by the task.
     */
    public function test_inactive_users_are_not_cached(): void {
        global $DB;
        $this->resetAfterTest(true);

        $gen       = $this->getDataGenerator();
        $active    = $gen->create_user(['deleted' => 0, 'suspended' => 0, 'confirmed' => 1]);
        $suspended = $gen->create_user(['suspended' => 1]);
        $deleted   = $gen->create_user();
        $DB->set_field('user', 'deleted', 1, ['id' => $deleted->id]);

        $cache = \cache::make('local_datacurso_ratings', 'recommendations');
        $cache->purge();

        $this->expectOutputRegex('/Finished updating cache/');
        $task = new \local_datacurso_ratings\task\update_recommendations_cache();
        $task->execute();

        $this->assertNotFalse($cache->get("user_{$active->id}"));
        $this->assertFalse($cache->get("user_{$suspended->id}"));
        $this->assertFalse($cache->get("user_{$deleted->id}"));
    }
}
