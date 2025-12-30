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
use context_module;

/**
 * Web service to obtain detailed feedback on a specific activity.
 *
 * @package    local_datacurso_ratings
 * @copyright  2025 Industria Elearning <info@industriaelearning.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_activity_comments extends external_api {
    /**
     * Function input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'page' => new external_value(PARAM_INT, 'Page number for pagination', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Items per page', VALUE_DEFAULT, 20),
            'search' => new external_value(PARAM_TEXT, 'Search text in comments', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Main logic: get paginated comments and statistics.
     *
     * @param int $cmid Course module ID
     * @param int $page Page number
     * @param int $perpage Items per page
     * @param string $search Optional search text
     * @return array Comments, pagination, statistics and activity information
     */
    public static function execute($cmid, $page = 0, $perpage = 20, $search = '') {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'page' => $page,
            'perpage' => $perpage,
            'search' => $search,
        ]);

        // Validate module context.
        $cm = get_coursemodule_from_id('', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('local/datacurso_ratings:viewcoursereport', $context);

        // Build the base query.
        $whereconditions = ['cmid = :cmid', 'feedback IS NOT NULL', "feedback != ''"];
        $sqlparams = ['cmid' => $params['cmid']];

        // Add search filter if it exists.
        if (!empty($params['search'])) {
            $whereconditions[] = $DB->sql_like('feedback', ':search', false, false);
            $sqlparams['search'] = '%' . $params['search'] . '%';
        }

        $wherestring = implode(' AND ', $whereconditions);

        // Get the total number of comments (for pagination).
        $totalcomments = $DB->count_records_select('local_datacurso_ratings', $wherestring, $sqlparams);

        // Get paginated comments.
        $offset = $params['page'] * $params['perpage'];
        $comments = $DB->get_records_select(
            'local_datacurso_ratings',
            $wherestring,
            $sqlparams,
            'timecreated DESC',
            'id, feedback, rating, timecreated',
            $offset,
            $params['perpage']
        );

        // Process comments.
        $processedcomments = [];
        foreach ($comments as $comment) {
            $processedcomments[] = [
                'id' => $comment->id,
                'feedback' => $comment->feedback,
                'rating' => (int)$comment->rating,
                'rating_type' => $comment->rating == 1 ? 'like' : 'dislike',
                'date' => userdate($comment->timecreated, get_string('strftimedatetimeshort')),
                'timestamp' => $comment->timecreated,
            ];
        }

        // Calculate statistics.
        $statistics = self::calculate_statistics($params['cmid'], $params['search']);

        return [
            'comments' => $processedcomments,
            'pagination' => [
                'total' => $totalcomments,
                'page' => $params['page'],
                'perpage' => $params['perpage'],
                'totalpages' => ceil($totalcomments / $params['perpage']),
                'hasmore' => ($offset + $params['perpage']) < $totalcomments,
            ],
            'statistics' => $statistics,
            'activity' => [
                'cmid' => $params['cmid'],
                'name' => $cm->name,
                'modname' => $cm->modname,
            ],
        ];
    }

    /**
     * Calculate comment statistics.
     *
     * @param int $cmid Course module ID
     * @param string $search Optional search text
     * @return array Statistics data (totals, averages, keywords)
     */
    private static function calculate_statistics($cmid, $search = '') {
        global $DB;

        // Build conditions.
        $whereconditions = ['cmid = :cmid', 'feedback IS NOT NULL', "feedback != ''"];
        $sqlparams = ['cmid' => $cmid];

        if (!empty($search)) {
            $whereconditions[] = $DB->sql_like('feedback', ':search', false, false);
            $sqlparams['search'] = '%' . $search . '%';
        }

        $wherestring = implode(' AND ', $whereconditions);

        // Get all comments for analysis.
        $allcomments = $DB->get_records_select(
            'local_datacurso_ratings',
            $wherestring,
            $sqlparams,
            '',
            'feedback, rating'
        );

        $totalcomments = count($allcomments);
        $likecomments = 0;
        $dislikecomments = 0;
        $alltext = '';

        foreach ($allcomments as $comment) {
            if ($comment->rating == 1) {
                $likecomments++;
            } else {
                $dislikecomments++;
            }
            $alltext .= ' ' . strtolower($comment->feedback);
        }

        // Keyword analysis (most frequent words).
        $keywords = self::extract_keywords($alltext);

        return [
            'total_comments' => $totalcomments,
            'like_comments' => $likecomments,
            'dislike_comments' => $dislikecomments,
            'avg_length' => $totalcomments > 0 ? round(strlen($alltext) / $totalcomments) : 0,
            'keywords' => $keywords,
        ];
    }

    /**
     * Extract most frequent keywords.
     *
     * @param string $text Text to analyze
     * @return array List of keywords with their frequency.
     *               Each element is an associative array:
     *               - word (string) Keyword
     *               - frequency (int) Word frequency
     */
    private static function extract_keywords($text) {
        // Words to ignore (stop words in Spanish and English).
        $stopwords = [
            'el', 'la', 'de', 'que', 'y', 'a', 'en', 'un', 'es', 'se', 'no', 'te', 'lo', 'le', 'da', 'su',
            'por', 'son', 'con', 'para', 'como', 'las', 'del', 'los', 'una', 'pero', 'sus',
            'the', 'and', 'is', 'it', 'to', 'of', 'this', 'that', 'not', 'are', 'was', 'be', 'have',
            'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might',
            'can', 'cant', 'dont', 'wont', 'very', 'too', 'much', 'more', 'most',
        ];

        // Clean and divide the text.
        $text = preg_replace('/[^\p{L}\s]/u', ' ', $text);
        $words = preg_split('/\s+/', trim($text));

        // Count frequencies.
        $wordcount = [];
        foreach ($words as $word) {
            $word = trim(strtolower($word));
            if (strlen($word) > 2 && !in_array($word, $stopwords)) {
                $wordcount[$word] = isset($wordcount[$word]) ? $wordcount[$word] + 1 : 1;
            }
        }

        // Order by frequency and get the top 10.
        arsort($wordcount);
        $keywords = [];
        $count = 0;
        foreach ($wordcount as $word => $frequency) {
            if ($count >= 10) {
                break;
            }
            $keywords[] = [
                'word' => $word,
                'frequency' => $frequency,
            ];
            $count++;
        }

        return $keywords;
    }

    /**
     * Structure of exit.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'comments' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Comment ID'),
                    'feedback' => new external_value(PARAM_RAW, 'Comment text'),
                    'rating' => new external_value(PARAM_INT, 'Rating value (0 or 1)'),
                    'rating_type' => new external_value(PARAM_TEXT, 'Rating type (like/dislike)'),
                    'date' => new external_value(PARAM_TEXT, 'Formatted date'),
                    'timestamp' => new external_value(PARAM_INT, 'Unix timestamp'),
                ])
            ),
            'pagination' => new external_single_structure([
                'total' => new external_value(PARAM_INT, 'Total comments'),
                'page' => new external_value(PARAM_INT, 'Current page'),
                'perpage' => new external_value(PARAM_INT, 'Items per page'),
                'totalpages' => new external_value(PARAM_INT, 'Total pages'),
                'hasmore' => new external_value(PARAM_BOOL, 'Has more pages'),
            ]),
            'statistics' => new external_single_structure([
                'total_comments' => new external_value(PARAM_INT, 'Total number of comments'),
                'like_comments' => new external_value(PARAM_INT, 'Comments with like rating'),
                'dislike_comments' => new external_value(PARAM_INT, 'Comments with dislike rating'),
                'avg_length' => new external_value(PARAM_INT, 'Average comment length'),
                'keywords' => new external_multiple_structure(
                    new external_single_structure([
                        'word' => new external_value(PARAM_TEXT, 'Keyword'),
                        'frequency' => new external_value(PARAM_INT, 'Word frequency'),
                    ])
                ),
            ]),
            'activity' => new external_single_structure([
                'cmid' => new external_value(PARAM_INT, 'Course module ID'),
                'name' => new external_value(PARAM_TEXT, 'Activity name'),
                'modname' => new external_value(PARAM_TEXT, 'Module name'),
            ]),
        ]);
    }
}
