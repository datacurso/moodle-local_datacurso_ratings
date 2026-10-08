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

namespace local_datacurso_ratings\recommendations;

/**
 * Recommendation service for local_datacurso_ratings plugin.
 *
 * This service calculates course recommendations based on user preferences,
 * category engagement, and general activity satisfaction ratios.
 *
 * @package    local_datacurso_ratings
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class service {
    /**
     * Get the like ratio across all ratings, of the site or of a tenant.
     *
     * Used as the category preference fallback when a user has no ratings in a
     * category. Callers that process many users should compute it once per tenant and pass
     * it to get_recommendations_for_user() instead of recomputing it per user.
     *
     * @param int|null $tenantid Tenant whose ratings count; null counts every rating of the site.
     * @return float Ratio in 0..1; 0.5 when there are no ratings at all.
     */
    public static function get_global_ratio(?int $tenantid = null): float {
        global $DB;

        // Moodle refuses a parameter the query does not use, so it only goes with the condition.
        [$where, $params] = $tenantid === null
            ? ['', []]
            : ['WHERE tenant_id IN (:tenantid, :notenant)', [
                'tenantid' => $tenantid,
                'notenant' => \local_datacurso_ratings\local\tenancy::NO_TENANT,
            ]];
        $global = $DB->get_record_sql("
            SELECT
                SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) AS likes,
                SUM(CASE WHEN rating = 0 THEN 1 ELSE 0 END) AS dislikes
            FROM {local_datacurso_ratings}
            {$where}
        ", $params);
        $globallikes = (int)($global->likes ?? 0);
        $globaldislikes = (int)($global->dislikes ?? 0);

        return ($globallikes + $globaldislikes) > 0
            ? ($globallikes / ($globallikes + $globaldislikes))
            : 0.5;
    }

    /**
     * Get recommended courses for a specific user.
     *
     * @param int $userid The user ID.
     * @param int $limit  Maximum number of recommendations to return.
     * @param float|null $globalratio Precomputed like ratio of the tenant of the user (see
     *                                get_global_ratio()); null computes it from the database.
     * @return array The list of recommended courses.
     */
    public static function get_recommendations_for_user(int $userid, int $limit = 5, ?float $globalratio = null): array {
        global $DB;

        // On Workplace the preferences and the fallback ratio are those of the tenant of the user.
        $tenantid = \local_datacurso_ratings\local\tenancy::get_tenant_id($userid);
        $user = \core_user::get_user($userid);
        if (!$user) {
            return [];
        }

        // Step 1: User preferences by category.
        $sqlusercats = "
            SELECT r.categoryid,
                   SUM(CASE WHEN r.rating = 1 THEN 1 ELSE 0 END) AS likes,
                   SUM(CASE WHEN r.rating = 0 THEN 1 ELSE 0 END) AS dislikes
              FROM {local_datacurso_ratings} r
             WHERE r.userid = :userid
               AND r.tenant_id IN (:tenantid, :notenant)
          GROUP BY r.categoryid
        ";

        $catprefs = $DB->get_records_sql($sqlusercats, [
            'userid' => $userid,
            'tenantid' => $tenantid,
            'notenant' => \local_datacurso_ratings\local\tenancy::NO_TENANT,
        ]);

        $categorypref = [];
        foreach ($catprefs as $c) {
            $likes = (int)($c->likes ?? 0);
            $dislikes = (int)($c->dislikes ?? 0);
            $total = $likes + $dislikes;
            $categorypref[$c->categoryid] = $total > 0 ? ($likes / $total) : null;
        }

        // Step 2: Global rating ratio of the tenant (computed here unless the caller already has it).
        if ($globalratio === null) {
            $globalratio = self::get_global_ratio($tenantid);
        }

        // Step 3: Get courses the user is already enrolled in (to exclude).
        $enrolledids = [];
        if (function_exists('enrol_get_users_courses')) {
            $enrolledids = array_keys(enrol_get_users_courses($userid, true));
        }

        // Step 4: Load visible courses (with cache).
        $cache = \cache::make('local_datacurso_ratings', 'recommendations');
        // The dataset carries the visibility of each course since 1.1.0-wp, hence the new key.
        $cachekey = "courses_dataset_v2";
        $courses = $cache->get($cachekey);

        if (!$courses) {
            // Optimized query: only last 300 active courses.
            $sql = "
                SELECT c.id AS courseid, c.fullname, c.category, c.visible, c.timemodified,
                       COALESCE(SUM(CASE WHEN r.rating = 1 THEN 1 ELSE 0 END), 0) AS likes,
                       COALESCE(SUM(CASE WHEN r.rating = 0 THEN 1 ELSE 0 END), 0) AS dislikes
                  FROM {course} c
             LEFT JOIN {local_datacurso_ratings} r ON r.courseid = c.id
                 WHERE c.visible = 1
                   AND c.id <> :siteid
              GROUP BY c.id, c.fullname, c.category, c.visible, c.timemodified
              ORDER BY c.timemodified DESC
              LIMIT 300
            ";
            $courses = $DB->get_records_sql($sql, ['siteid' => SITEID]);
            $cache->set($cachekey, $courses);
        }

        // Step 5: Calculate score for each course.
        $recommendations = [];
        foreach ($courses as $course) {
            $courseid = (int)$course->courseid;
            if (in_array($courseid, $enrolledids, true)) {
                continue;
            }

            // Only courses the user may see listed: on Workplace the course list of a tenant
            // category is only visible to the users of that tenant.
            $listed = (object)['id' => $courseid, 'category' => (int)$course->category, 'visible' => (int)$course->visible];
            if (!\core_course_category::can_view_course_info($listed, $user)) {
                continue;
            }

            $likes = (int)$course->likes;
            $dislikes = (int)$course->dislikes;
            $total = $likes + $dislikes;
            $satisfaction = $total > 0 ? round(($likes / $total) * 100, 2) : 0.0;

            $catid = (int)$course->category;
            $usercatratio = $categorypref[$catid] ?? null;
            $catratio = $usercatratio !== null ? $usercatratio : $globalratio;
            $catpercent = round($catratio * 100, 2);

            $score = (0.6 * $catpercent) + (0.4 * $satisfaction);

            $recommendations[] = [
                'courseid' => $courseid,
                'fullname' => $course->fullname,
                'categoryid' => $catid,
                'course_satisfaction' => $satisfaction,
                'category_preference_pct' => $catpercent,
                'score' => round($score, 2),
            ];
        }

        // Step 6: Sort and filter.
        usort($recommendations, static fn($a, $b) => $b['score'] <=> $a['score']);
        $filtered = array_filter($recommendations, static fn($r) => $r['category_preference_pct'] >= 80);

        // Step 7: Return top N recommendations.
        return array_slice(array_values($filtered), 0, max(0, $limit));
    }
}
