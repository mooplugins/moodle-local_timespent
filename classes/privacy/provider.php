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
 * Privacy provider for local_timespent.
 *
 * @package    local_timespent
 * @copyright  2026 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_timespent\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy subsystem implementation.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe stored personal data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_timespent_session', [
            'register' => 'privacy:metadata:local_timespent_session:register',
            'userid' => 'privacy:metadata:local_timespent_session:userid',
            'login' => 'privacy:metadata:local_timespent_session:login',
            'logout' => 'privacy:metadata:local_timespent_session:logout',
            'duration' => 'privacy:metadata:local_timespent_session:duration',
        ], 'privacy:metadata:local_timespent_session');

        $collection->add_database_table('local_timespent_aggregate', [
            'register' => 'privacy:metadata:local_timespent_aggregate:register',
            'userid' => 'privacy:metadata:local_timespent_aggregate:userid',
            'duration' => 'privacy:metadata:local_timespent_aggregate:duration',
            'lastsessionlogout' => 'privacy:metadata:local_timespent_aggregate:lastsessionlogout',
        ], 'privacy:metadata:local_timespent_aggregate');

        return $collection;
    }

    /**
     * Get contexts containing user data.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {local_timespent_session} s ON s.register = ctx.instanceid AND ctx.contextlevel = :level1
                 WHERE s.userid = :userid1
                 UNION
                SELECT ctx.id
                  FROM {context} ctx
                  JOIN {local_timespent_aggregate} a ON a.register = ctx.instanceid AND ctx.contextlevel = :level2
                 WHERE a.userid = :userid2";

        $contextlist->add_from_sql($sql, [
            'level1' => CONTEXT_COURSE,
            'level2' => CONTEXT_COURSE,
            'userid1' => $userid,
            'userid2' => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Get users in a context.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_course) {
            return;
        }

        $sql = "SELECT userid FROM {local_timespent_session} WHERE register = :courseid
                UNION
                SELECT userid FROM {local_timespent_aggregate} WHERE register = :courseid2";
        $userlist->add_from_sql('userid', $sql, [
            'courseid' => $context->instanceid,
            'courseid2' => $context->instanceid,
        ]);
    }

    /**
     * Export user data for approved contexts.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (!$contextlist->count()) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_course) {
                continue;
            }
            $courseid = $context->instanceid;
            $sessions = $DB->get_records('local_timespent_session', [
                'register' => $courseid,
                'userid' => $userid,
            ]);
            $aggregates = $DB->get_records('local_timespent_aggregate', [
                'register' => $courseid,
                'userid' => $userid,
            ]);
            if ($sessions || $aggregates) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_timespent')],
                    (object) [
                        'sessions' => array_values($sessions),
                        'aggregates' => array_values($aggregates),
                    ]
                );
            }
        }
    }

    /**
     * Delete all data for all users in a context.
     *
     * @param \context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if (!$context instanceof \context_course) {
            return;
        }

        $DB->delete_records('local_timespent_session', ['register' => $context->instanceid]);
        $DB->delete_records('local_timespent_aggregate', ['register' => $context->instanceid]);
    }

    /**
     * Delete all user data for the specified user, in the approved contexts.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        if (!$contextlist->count()) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_course) {
                continue;
            }
            $DB->delete_records('local_timespent_session', [
                'register' => $context->instanceid,
                'userid' => $userid,
            ]);
            $DB->delete_records('local_timespent_aggregate', [
                'register' => $context->instanceid,
                'userid' => $userid,
            ]);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof \context_course) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params = array_merge(['courseid' => $context->instanceid], $userparams);
        $DB->delete_records_select('local_timespent_session', "register = :courseid AND userid $usersql", $params);
        $DB->delete_records_select('local_timespent_aggregate', "register = :courseid AND userid $usersql", $params);
    }
}
