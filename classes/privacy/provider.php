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

namespace local_datacurso_ratings\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem implementation for local_datacurso_ratings.
 *
 * Every rating belongs to the module context of the rated course module.
 *
 * @package   local_datacurso_ratings
 * @category  privacy
 * @copyright 2025 Industria Elearning <info@industriaelearning.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the types of personal data stored or sent by this plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_datacurso_ratings',
            [
                'userid'       => 'privacy:metadata:local_datacurso_ratings:userid',
                'cmid'         => 'privacy:metadata:local_datacurso_ratings:cmid',
                'courseid'     => 'privacy:metadata:local_datacurso_ratings:courseid',
                'categoryid'   => 'privacy:metadata:local_datacurso_ratings:categoryid',
                'rating'       => 'privacy:metadata:local_datacurso_ratings:rating',
                'feedback'     => 'privacy:metadata:local_datacurso_ratings:feedback',
                'timecreated'  => 'privacy:metadata:local_datacurso_ratings:timecreated',
                'timemodified' => 'privacy:metadata:local_datacurso_ratings:timemodified',
            ],
            'privacy:metadata:local_datacurso_ratings'
        );

        $collection->add_external_location_link(
            'datacurso_ai',
            [
                'feedback'        => 'privacy:metadata:datacurso_ai:feedback',
                'course'          => 'privacy:metadata:datacurso_ai:course',
                'activity'        => 'privacy:metadata:datacurso_ai:activity',
                'activity_type'   => 'privacy:metadata:datacurso_ai:activity_type',
                'approvalpercent' => 'privacy:metadata:datacurso_ai:approvalpercent',
                'userid'          => 'privacy:metadata:datacurso_ai:userid',
            ],
            'privacy:metadata:datacurso_ai'
        );

        return $collection;
    }

    /**
     * Get the module contexts of the course modules rated by the specified user.
     *
     * @param int $userid The user ID.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {local_datacurso_ratings} r
                  JOIN {context} ctx ON ctx.instanceid = r.cmid AND ctx.contextlevel = :contextlevel
                 WHERE r.userid = :userid";
        $params = [
            'contextlevel' => CONTEXT_MODULE,
            'userid' => $userid,
        ];

        $contextlist->add_from_sql($sql, $params);
        return $contextlist;
    }

    /**
     * Get the list of users who rated the course module of the given context.
     *
     * @param userlist $userlist The userlist to add the users to.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }

        $sql = "SELECT r.userid
                  FROM {local_datacurso_ratings} r
                  JOIN {context} ctx ON ctx.instanceid = r.cmid AND ctx.contextlevel = :contextlevel
                 WHERE ctx.id = :contextid";
        $params = [
            'contextlevel' => CONTEXT_MODULE,
            'contextid' => $context->id,
        ];

        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Export the user's rating for every approved module context.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $contextids = $contextlist->get_contextids();
        if (empty($contextids)) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        [$insql, $inparams] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED);

        $sql = "SELECT r.id, r.rating, r.feedback, r.timecreated, r.timemodified, ctx.id AS contextid
                  FROM {local_datacurso_ratings} r
                  JOIN {context} ctx ON ctx.instanceid = r.cmid AND ctx.contextlevel = :contextlevel
                 WHERE r.userid = :userid AND ctx.id $insql
              ORDER BY r.id ASC";
        $params = ['contextlevel' => CONTEXT_MODULE, 'userid' => $userid] + $inparams;

        $subcontext = [get_string('privacy:subcontext:ratings', 'local_datacurso_ratings')];
        $ratings = $DB->get_recordset_sql($sql, $params);
        foreach ($ratings as $rating) {
            $context = \context::instance_by_id($rating->contextid);
            $data = (object)[
                'rating'       => get_string($rating->rating ? 'like' : 'dislike', 'local_datacurso_ratings'),
                'feedback'     => $rating->feedback,
                'timecreated'  => transform::datetime($rating->timecreated),
                'timemodified' => transform::datetime($rating->timemodified),
            ];
            writer::with_context($context)->export_data($subcontext, $data);
        }
        $ratings->close();
    }

    /**
     * Delete all ratings of the course module of the given context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }

        $DB->delete_records('local_datacurso_ratings', ['cmid' => $context->instanceid]);
    }

    /**
     * Delete the user's ratings in every approved module context.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $DB->delete_records('local_datacurso_ratings', ['cmid' => $context->instanceid, 'userid' => $userid]);
        }
    }

    /**
     * Delete the ratings of the listed users in the module context of the userlist.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['cmid'] = $context->instanceid;
        $DB->delete_records_select('local_datacurso_ratings', "cmid = :cmid AND userid $insql", $params);
    }
}
