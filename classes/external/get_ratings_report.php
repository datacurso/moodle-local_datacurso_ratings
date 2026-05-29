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

namespace local_datacurso_ratings\external;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");

use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;
use external_api;
use context_system;

/**
 * Web service to get general report of activities with ratings.
 *
 * @package    local_datacurso_ratings
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_ratings_report extends external_api {
    /**
     * Function input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'page' => new external_value(PARAM_INT, 'Zero-based page number', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Courses per page', VALUE_DEFAULT, 5),
            'searchactivity' => new external_value(PARAM_TEXT, 'Activity search text', VALUE_DEFAULT, ''),
            'searchcourse' => new external_value(PARAM_TEXT, 'Course search text', VALUE_DEFAULT, ''),
            'categoryid' => new external_value(PARAM_INT, 'Category ID filter', VALUE_DEFAULT, 0),
            'datefrom' => new external_value(PARAM_TEXT, 'Start date filter (YYYY-MM-DD)', VALUE_DEFAULT, ''),
            'dateto' => new external_value(PARAM_TEXT, 'End date filter (YYYY-MM-DD)', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Main logic: queries and returns the information.
     *
     * @param int $page Zero-based page number.
     * @param int $perpage Courses per page.
     * @param string $searchactivity Activity search text.
     * @param string $searchcourse Course search text.
     * @param int $categoryid Category ID filter.
     * @param string $datefrom Start date filter (YYYY-MM-DD).
     * @param string $dateto End date filter (YYYY-MM-DD).
     * @return array
     */
    public static function execute(
        $page = 0,
        $perpage = 5,
        $searchactivity = '',
        $searchcourse = '',
        $categoryid = 0,
        $datefrom = '',
        $dateto = ''
    ) {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'page' => $page,
            'perpage' => $perpage,
            'searchactivity' => $searchactivity,
            'searchcourse' => $searchcourse,
            'categoryid' => $categoryid,
            'datefrom' => $datefrom,
            'dateto' => $dateto,
        ]);

        // Validate context.
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:config', $context);

        $allowedpagesizes = [5, 10, 25, 50, 100];
        if (!in_array((int)$params['perpage'], $allowedpagesizes, true)) {
            $params['perpage'] = 5;
        }

        if ((int)$params['page'] < 0) {
            $params['page'] = 0;
        }

        // Detect DB type for compatibility.
        $dbfamily = $DB->get_dbfamily();

        // Handle GROUP_CONCAT / STRING_AGG depending on DB type.
        if ($dbfamily === 'postgres') {
            $concatcomments = "STRING_AGG(DISTINCT r.feedback, ' / ') AS comentarios";
        } else {
            $concatcomments = "GROUP_CONCAT(DISTINCT r.feedback SEPARATOR ' / ') AS comentarios";
        }

        $sqlparams = [];
        $whereparts = [];

        if (!empty($params['categoryid'])) {
            $whereparts[] = 'c.category = :categoryid';
            $sqlparams['categoryid'] = (int)$params['categoryid'];
        }

        if (!empty($params['searchcourse'])) {
            $whereparts[] = $DB->sql_like('c.fullname', ':searchcourse', false, false);
            $sqlparams['searchcourse'] = '%' . trim($params['searchcourse']) . '%';
        }

        $datefromts = self::parse_date_boundary($params['datefrom'], false);
        $datetots = self::parse_date_boundary($params['dateto'], true);

        if ($datefromts !== null) {
            $whereparts[] = 'r.timemodified >= :datefromts';
            $sqlparams['datefromts'] = $datefromts;
        }

        if ($datetots !== null) {
            $whereparts[] = 'r.timemodified <= :datetots';
            $sqlparams['datetots'] = $datetots;
        }

        $wheresql = '';
        if (!empty($whereparts)) {
            $wheresql = ' WHERE ' . implode(' AND ', $whereparts);
        }

        // Main SQL query (compatible with both MySQL and PostgreSQL).
        $sql = "
            SELECT r.cmid,
                   cm.course,
                   c.fullname AS coursename,
                   c.category AS categoryid,
                   SUM(CASE WHEN r.rating = 1 THEN 1 ELSE 0 END) AS likes,
                   SUM(CASE WHEN r.rating = 0 THEN 1 ELSE 0 END) AS dislikes,
                   ROUND(SUM(CASE WHEN r.rating = 1 THEN 1 ELSE 0 END) * 100.0
                   / NULLIF(COUNT(r.id), 0), 1) AS porcentaje_aprobacion,
                   {$concatcomments}
            FROM {local_datacurso_ratings} r
            JOIN {course_modules} cm ON cm.id = r.cmid
            JOIN {course} c ON c.id = cm.course
            {$wheresql}
            GROUP BY r.cmid, cm.course, c.fullname, c.category
            ORDER BY c.fullname ASC, r.cmid ASC
        ";

        $records = $DB->get_records_sql($sql, $sqlparams);
        $coursegroups = [];

        // Cache to reuse modinfo per course.
        $coursecache = [];

        foreach ($records as $r) {
            $courseid = $r->course;

            if (!isset($coursecache[$courseid])) {
                $coursecache[$courseid] = get_fast_modinfo($courseid);
            }

            $modinfo = $coursecache[$courseid];

            // Validate module existence.
            if (!$modinfo->cms || !isset($modinfo->cms[$r->cmid])) {
                continue;
            }

            $cm = $modinfo->get_cm($r->cmid);
            if (!$cm->uservisible) {
                continue;
            }

            if (!empty($params['searchactivity']) && stripos($cm->name, trim($params['searchactivity'])) === false) {
                continue;
            }

            // Prepare comments array.
            $commentsarray = [];
            if (!empty($r->comentarios)) {
                $commentsarray = explode(' / ', $r->comentarios);
            }

            if (!isset($coursegroups[$courseid])) {
                $coursegroups[$courseid] = [
                    'courseid' => (int)$courseid,
                    'courseName' => $r->coursename,
                    'categoryid' => (int)$r->categoryid,
                    'courseLikes' => 0,
                    'courseDislikes' => 0,
                    'courseActivities' => 0,
                    'courseTotal' => 0,
                    'courseSatisfaction' => '0.0',
                    'courseSatisfactionClass' => 'danger',
                    'activities' => [],
                ];
            }

            $likes = (int)$r->likes;
            $dislikes = (int)$r->dislikes;
            $totalratings = $likes + $dislikes;
            $approvalpercent = (float)$r->porcentaje_aprobacion;

            $coursegroups[$courseid]['courseLikes'] += $likes;
            $coursegroups[$courseid]['courseDislikes'] += $dislikes;
            $coursegroups[$courseid]['courseActivities']++;

            $coursegroups[$courseid]['activities'][] = [
                'activity' => $cm->name,
                'modname' => $cm->modname,
                'cmid' => (int)$cm->id,
                'url' => $cm->url ? $cm->url->out(false) : '',
                'likes' => $likes,
                'dislikes' => $dislikes,
                'total_ratings' => $totalratings,
                'has_ratings' => $totalratings > 0,
                'has_comments' => !empty($commentsarray),
                'approvalpercent' => $approvalpercent,
                'formatted_percentage' => number_format($approvalpercent, 1) . '%',
                'satisfaction_class' => self::get_satisfaction_class($approvalpercent),
                'comments' => $commentsarray,
            ];
        }

        $courses = array_values($coursegroups);

        foreach ($courses as $index => $course) {
            $coursetotal = $course['courseLikes'] + $course['courseDislikes'];
            $courses[$index]['courseTotal'] = $coursetotal;
            $courses[$index]['courseSatisfaction'] = $coursetotal > 0
                ? number_format(($course['courseLikes'] * 100) / $coursetotal, 1)
                : '0.0';
            $courses[$index]['courseSatisfactionClass'] = self::get_satisfaction_class((float)$courses[$index]['courseSatisfaction']);
        }

        $summary = self::build_summary($courses);

        $totalcourses = count($courses);
        $perpage = (int)$params['perpage'];
        $totalpages = $totalcourses > 0 ? (int)ceil($totalcourses / $perpage) : 0;
        $page = (int)$params['page'];

        if ($totalpages > 0 && $page >= $totalpages) {
            $page = $totalpages - 1;
        }

        $offset = $page * $perpage;
        $pagedcourses = array_slice($courses, $offset, $perpage);

        return [
            'courses' => $pagedcourses,
            'summary' => $summary,
            'has_data' => !empty($courses),
            'pagination' => [
                'page' => $page,
                'displaypage' => $totalpages > 0 ? $page + 1 : 0,
                'perpage' => $perpage,
                'totalpages' => $totalpages,
                'totalcourses' => $totalcourses,
                'hasprev' => $page > 0,
                'hasnext' => $totalpages > 0 && $page < ($totalpages - 1),
                'prevpage' => $page > 0 ? $page - 1 : 0,
                'nextpage' => $totalpages > 0 && $page < ($totalpages - 1) ? $page + 1 : $page,
            ],
        ];
    }

    /**
     * Parse a date input and return the timestamp boundary.
     *
     * @param string $date Date in YYYY-MM-DD format.
     * @param bool $isend Whether to return end-of-day timestamp.
     * @return int|null
     */
    private static function parse_date_boundary(string $date, bool $isend): ?int {
        $date = trim($date);
        if ($date === '') {
            return null;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new \invalid_parameter_exception('Invalid date format. Expected YYYY-MM-DD.');
        }

        $timesuffix = $isend ? '23:59:59' : '00:00:00';
        $timestamp = strtotime($date . ' ' . $timesuffix);
        if ($timestamp === false) {
            throw new \invalid_parameter_exception('Invalid date value.');
        }

        return $timestamp;
    }

    /**
     * Build summary data for all filtered courses.
     *
     * @param array $courses Grouped courses.
     * @return array
     */
    private static function build_summary(array $courses): array {
        $totallikes = 0;
        $totaldislikes = 0;
        $totalactivities = 0;
        $activitieswithratings = 0;

        foreach ($courses as $course) {
            $totallikes += (int)$course['courseLikes'];
            $totaldislikes += (int)$course['courseDislikes'];
            $totalactivities += (int)$course['courseActivities'];

            foreach ($course['activities'] as $activity) {
                if (!empty($activity['has_ratings'])) {
                    $activitieswithratings++;
                }
            }
        }

        $totalratings = $totallikes + $totaldislikes;
        $overallsatisfaction = $totalratings > 0 ? (($totallikes * 100) / $totalratings) : 0;

        return [
            'total_courses' => count($courses),
            'total_activities' => $totalactivities,
            'activities_with_ratings' => $activitieswithratings,
            'total_ratings' => $totalratings,
            'total_likes' => $totallikes,
            'total_dislikes' => $totaldislikes,
            'overall_satisfaction' => number_format($overallsatisfaction, 1),
            'satisfaction_class' => self::get_satisfaction_class($overallsatisfaction),
        ];
    }

    /**
     * Get class name based on satisfaction percentage.
     *
     * @param float $percentage
     * @return string
     */
    private static function get_satisfaction_class(float $percentage): string {
        if ($percentage >= 80) {
            return 'success';
        }

        if ($percentage >= 60) {
            return 'warning';
        }

        if ($percentage >= 40) {
            return 'info';
        }

        return 'danger';
    }

    /**
     * Output structure.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'courses' => new external_multiple_structure(
                new external_single_structure([
                    'courseid' => new external_value(PARAM_INT, 'Course ID'),
                    'courseName' => new external_value(PARAM_TEXT, 'Course name'),
                    'categoryid' => new external_value(PARAM_INT, 'Category ID'),
                    'courseLikes' => new external_value(PARAM_INT, 'Course likes total'),
                    'courseDislikes' => new external_value(PARAM_INT, 'Course dislikes total'),
                    'courseActivities' => new external_value(PARAM_INT, 'Total activities in course (with ratings)'),
                    'courseTotal' => new external_value(PARAM_INT, 'Total ratings in course'),
                    'courseSatisfaction' => new external_value(PARAM_TEXT, 'Course satisfaction percentage formatted'),
                    'courseSatisfactionClass' => new external_value(PARAM_TEXT, 'CSS class for course satisfaction'),
                    'activities' => new external_multiple_structure(
                        new external_single_structure([
                            'activity' => new external_value(PARAM_TEXT, 'Activity name'),
                            'modname' => new external_value(PARAM_TEXT, 'Module type'),
                            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
                            'url' => new external_value(PARAM_URL, 'Activity URL', VALUE_OPTIONAL),
                            'likes' => new external_value(PARAM_INT, 'Number of likes'),
                            'dislikes' => new external_value(PARAM_INT, 'Number of dislikes'),
                            'total_ratings' => new external_value(PARAM_INT, 'Total ratings'),
                            'has_ratings' => new external_value(PARAM_BOOL, 'Has ratings'),
                            'has_comments' => new external_value(PARAM_BOOL, 'Has comments'),
                            'approvalpercent' => new external_value(PARAM_FLOAT, 'Approval percentage'),
                            'formatted_percentage' => new external_value(PARAM_TEXT, 'Formatted approval percentage'),
                            'satisfaction_class' => new external_value(PARAM_TEXT, 'Satisfaction CSS class'),
                            'comments' => new external_multiple_structure(
                                new external_value(PARAM_RAW, 'Individual comment'),
                                'List of comments',
                                VALUE_OPTIONAL
                            ),
                        ])
                    ),
                ])
            ),
            'summary' => new external_single_structure([
                'total_courses' => new external_value(PARAM_INT, 'Total courses'),
                'total_activities' => new external_value(PARAM_INT, 'Total activities'),
                'activities_with_ratings' => new external_value(PARAM_INT, 'Activities with ratings'),
                'total_ratings' => new external_value(PARAM_INT, 'Total ratings'),
                'total_likes' => new external_value(PARAM_INT, 'Total likes'),
                'total_dislikes' => new external_value(PARAM_INT, 'Total dislikes'),
                'overall_satisfaction' => new external_value(PARAM_TEXT, 'Overall satisfaction formatted'),
                'satisfaction_class' => new external_value(PARAM_TEXT, 'Satisfaction CSS class'),
            ]),
            'has_data' => new external_value(PARAM_BOOL, 'Whether filtered report has data'),
            'pagination' => new external_single_structure([
                'page' => new external_value(PARAM_INT, 'Zero-based page index'),
                'displaypage' => new external_value(PARAM_INT, 'One-based page index for UI'),
                'perpage' => new external_value(PARAM_INT, 'Courses per page'),
                'totalpages' => new external_value(PARAM_INT, 'Total pages'),
                'totalcourses' => new external_value(PARAM_INT, 'Total filtered courses'),
                'hasprev' => new external_value(PARAM_BOOL, 'Has previous page'),
                'hasnext' => new external_value(PARAM_BOOL, 'Has next page'),
                'prevpage' => new external_value(PARAM_INT, 'Previous page index'),
                'nextpage' => new external_value(PARAM_INT, 'Next page index'),
            ]),
        ]);
    }
}
