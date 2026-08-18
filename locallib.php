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
 * Local library functions for local_timespent.
 *
 * @package    local_timespent
 * @copyright  2026 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Require permission to view the timespent report.
 *
 * @param context|null $context
 * @return void
 * @throws required_capability_exception
 */
function local_timespent_require_view_report(?context $context = null): void {
    if ($context === null) {
        $context = context_system::instance();
    }

    if (has_capability('local/timespent:viewreport', $context)) {
        return;
    }

    // Allow staff who already have the shared reports capability on this product stack.
    $pluginman = core_plugin_manager::instance();
    if ($pluginman->get_plugin_info('local_slmscommon')
            && has_capability('local/slmscommon:viewreports', $context)) {
        return;
    }

    throw new required_capability_exception($context, 'local/timespent:viewreport', 'nopermissions', '');
}

/**
 * Calculate and save all sessions for a user in a course, then return summary data.
 *
 * $courseorregister is historically an object with ->id set to the course id
 * (column name in DB remains "register" for upgrade compatibility). Optional
 * ->offlinesessions enables offline session aggregation when present.
 *
 * @param stdClass $courseorregister Object with id (course id) and optional offlinesessions.
 * @param int $userid
 * @param int $fromtime
 * @param int|bool $formatted Return human-readable duration when true.
 * @param int $totime
 * @return array duration and lastsessionlogout keys
 */
function local_timespent_build_new_user_sessions($courseorregister, $userid, $fromtime = 0, $formatted = 0, $totime = 0) {
    global $DB;

    $oldestlogentrytime = local_timespent_get_user_oldest_log_entry_timestamp($userid);
    local_timespent_delete_user_online_sessions($courseorregister, $userid, $oldestlogentrytime);
    local_timespent_delete_user_aggregates($courseorregister, $userid);
    $totallogentriescount = 0;
    $logentries = local_timespent_get_user_log_entries_in_courses(
        $userid,
        $fromtime,
        [$courseorregister->id],
        $totallogentriescount,
        $totime
    );
    $sessiontimeoutseconds = 15 * 60;
    $prevlogentry = null;
    $sessionstarttimestamp = null;
    $logentriescount = 0;
    $newsessionscount = 0;
    $sessionlastentrytimestamp = 0;
    $logentry = null;

    if (is_array($logentries) && count($logentries) > 0) {
        foreach ($logentries as $logentry) {
            $logentriescount++;
            if (!$prevlogentry) {
                $prevlogentry = $logentry;
                $sessionstarttimestamp = $logentry->timecreated;
                continue;
            }
            if (($logentry->timecreated - $prevlogentry->timecreated) > $sessiontimeoutseconds) {
                $newsessionscount++;
                $sessionlastentrytimestamp = $prevlogentry->timecreated;
                $estimatedsessionend = $sessionlastentrytimestamp + $sessiontimeoutseconds / 2;
                local_timespent_save_session($courseorregister, $userid, $sessionstarttimestamp, $estimatedsessionend);
                $sessionstarttimestamp = $logentry->timecreated;
            }
            $prevlogentry = $logentry;
        }
        if (
            $logentry
                && $logentry->timecreated > $sessionlastentrytimestamp
                && (time() - $logentry->timecreated) > $sessiontimeoutseconds
        ) {
            $newsessionscount++;
            $sessionlastentrytimestamp = $logentry->timecreated;
            $estimatedsessionend = $sessionlastentrytimestamp + $sessiontimeoutseconds / 2;
            local_timespent_save_session($courseorregister, $userid, $sessionstarttimestamp, $estimatedsessionend);
        }
    }

    if ($newsessionscount) {
        local_timespent_update_user_aggregates($courseorregister, $userid);
    }

    $useraggr = $DB->get_record('local_timespent_aggregate', [
        'register' => $courseorregister->id,
        'userid' => $userid,
        'grandtotal' => 1,
    ]);

    $duration = ($useraggr) ? $useraggr->duration : 0;
    if ($formatted) {
        $duration = ($useraggr) ? local_timespent_format_duration($useraggr->duration) : '-';
    }
    $lastsessionlogout = ($useraggr)
        ? local_timespent_format_datetime($useraggr->lastsessionlogout)
        : get_string('no_session', 'local_timespent');

    return ['duration' => $duration, 'lastsessionlogout' => $lastsessionlogout];
}

/**
 * Save a new session row.
 *
 * @param stdClass $courseorregister
 * @param int $userid
 * @param int $logintimestamp
 * @param int $logouttimestamp
 * @param bool $isonline
 * @param int|null $refcourseid
 * @param string|null $comments
 * @return void
 */
function local_timespent_save_session(
    $courseorregister,
    $userid,
    $logintimestamp,
    $logouttimestamp,
    $isonline = true,
    $refcourseid = null,
    $comments = null
) {
    global $DB;

    $session = new stdClass();
    $session->register = $courseorregister->id;
    $session->userid = $userid;
    $session->login = $logintimestamp;
    $session->logout = $logouttimestamp;
    $session->duration = ($logouttimestamp - $logintimestamp);
    $session->onlinesess = $isonline;
    $session->refcourse = $refcourseid;
    $session->comments = $comments;

    $DB->insert_record('local_timespent_session', $session);
}

/**
 * Recalculate and store aggregates for a user in a course.
 *
 * @param stdClass $courseorregister
 * @param int $userid
 * @return void
 */
function local_timespent_update_user_aggregates($courseorregister, $userid) {
    global $DB;

    $DB->delete_records('local_timespent_aggregate', ['userid' => $userid, 'register' => $courseorregister->id]);
    $aggregates = [];
    $queryparams = ['registerid' => $courseorregister->id, 'userid' => $userid];

    if (!empty($courseorregister->offlinesessions)) {
        $sql = 'SELECT sess.refcourse, sess.register, sess.userid, 0 AS onlinesess,'
            . ' SUM(sess.duration) AS duration, 0 AS total, 0 as grandtotal'
            . ' FROM {local_timespent_session} sess'
            . ' WHERE sess.onlinesess = 0 AND sess.register = :registerid AND sess.userid = :userid'
            . ' GROUP BY sess.register, sess.userid, sess.refcourse';
        $offlinepercourseaggregates = $DB->get_records_sql($sql, $queryparams);
        if ($offlinepercourseaggregates) {
            $aggregates = array_merge($aggregates, $offlinepercourseaggregates);
        }

        $sql = 'SELECT sess.register, sess.userid, 0 AS onlinesess, null AS refcourse,'
            . ' SUM(sess.duration) AS duration, 1 AS total, 0 as grandtotal'
            . ' FROM {local_timespent_session} sess'
            . ' WHERE sess.onlinesess = 0 AND sess.register = :registerid AND sess.userid = :userid'
            . ' GROUP BY sess.register, sess.userid';
        $totalofflineaggregate = $DB->get_record_sql($sql, $queryparams);
        if ($totalofflineaggregate) {
            $aggregates[] = $totalofflineaggregate;
        }
    }

    $sql = 'SELECT sess.register, sess.userid, 1 AS onlinesess, null AS refcourse,'
        . ' SUM(sess.duration) AS duration, 1 AS total, 0 as grandtotal'
        . ' FROM {local_timespent_session} sess'
        . ' WHERE sess.onlinesess = 1 AND sess.register = :registerid AND sess.userid = :userid'
        . ' GROUP BY sess.register, sess.userid';
    $onlineaggregate = $DB->get_record_sql($sql, $queryparams);
    if (!$onlineaggregate) {
        $onlineaggregate = new stdClass();
        $onlineaggregate->register = $courseorregister->id;
        $onlineaggregate->userid = $userid;
        $onlineaggregate->onlinesess = 1;
        $onlineaggregate->refcourse = null;
        $onlineaggregate->duration = 0;
        $onlineaggregate->total = 1;
        $onlineaggregate->grandtotal = 0;
    }
    $aggregates[] = $onlineaggregate;

    $sql = 'SELECT sess.register, sess.userid, null AS onlinesess, null AS refcourse,'
        . ' SUM(sess.duration) AS duration, 0 AS total, 1 as grandtotal'
        . ' FROM {local_timespent_session} sess'
        . ' WHERE sess.register = :registerid AND sess.userid = :userid'
        . ' GROUP BY sess.register, sess.userid';
    $grandtotalaggregate = $DB->get_record_sql($sql, $queryparams);

    if (!$grandtotalaggregate) {
        $grandtotalaggregate = new stdClass();
        $grandtotalaggregate->register = $courseorregister->id;
        $grandtotalaggregate->userid = $userid;
        $grandtotalaggregate->onlinesess = null;
        $grandtotalaggregate->refcourse = null;
        $grandtotalaggregate->duration = 0;
        $grandtotalaggregate->total = 0;
        $grandtotalaggregate->grandtotal = 1;
    }
    $grandtotalaggregate->lastsessionlogout = local_timespent_calculate_last_user_online_session_logout(
        $courseorregister,
        $userid
    );
    $aggregates[] = $grandtotalaggregate;

    foreach ($aggregates as $aggregate) {
        $DB->insert_record('local_timespent_aggregate', $aggregate);
    }
}

/**
 * End timestamp of the latest calculated online session for a user.
 *
 * @param stdClass $courseorregister
 * @param int $userid
 * @return int
 */
function local_timespent_calculate_last_user_online_session_logout($courseorregister, $userid) {
    global $DB;

    $queryparams = ['register' => $courseorregister->id, 'userid' => $userid];
    $lastsessionend = $DB->get_field_sql(
        'SELECT MAX(logout) FROM {local_timespent_session} WHERE register = ? AND userid = ? AND onlinesess = 1',
        $queryparams
    );
    if ($lastsessionend === false) {
        $lastsessionend = 0;
    }
    return $lastsessionend;
}

/**
 * Timestamp of the oldest site log entry for a user.
 *
 * @param int $userid
 * @return int|null
 */
function local_timespent_get_user_oldest_log_entry_timestamp($userid) {
    global $DB;

    $obj = $DB->get_record_sql(
        'SELECT MIN(timecreated) as oldestlogtime FROM {logstore_standard_log} WHERE userid = :userid',
        ['userid' => $userid],
        IGNORE_MISSING
    );
    if ($obj) {
        return $obj->oldestlogtime;
    }
    return null;
}

/**
 * Delete online sessions for a user in a course.
 *
 * @param stdClass $courseorregister
 * @param int $userid
 * @param int|null $onlydeleteafter When set, only delete sessions with login >= this timestamp.
 * @return void
 */
function local_timespent_delete_user_online_sessions($courseorregister, $userid, $onlydeleteafter = null) {
    global $DB;

    $params = ['userid' => $userid, 'register' => $courseorregister->id, 'onlinesess' => 1];
    if ($onlydeleteafter) {
        $where = 'userid = :userid AND register = :register AND onlinesess = :onlinesess AND login >= :lowerlimit';
        $params['lowerlimit'] = $onlydeleteafter;
        $DB->delete_records_select('local_timespent_session', $where, $params);
    } else {
        $DB->delete_records('local_timespent_session', $params);
    }
}

/**
 * Delete all aggregates for a user in a course.
 *
 * @param stdClass $courseorregister
 * @param int $userid
 * @return void
 */
function local_timespent_delete_user_aggregates($courseorregister, $userid) {
    global $DB;
    $DB->delete_records('local_timespent_aggregate', ['userid' => $userid, 'register' => $courseorregister->id]);
}

/**
 * Log entries for a user in given courses, oldest to newest.
 *
 * @param int $userid
 * @param int $fromtime
 * @param array $courseids
 * @param int $logcount Count of records, passed by reference.
 * @param int $totime
 * @return array
 */
function local_timespent_get_user_log_entries_in_courses($userid, $fromtime, $courseids, &$logcount, $totime = 0) {
    global $DB;

    if (!$fromtime) {
        $fromtime = 0;
    }
    if (!$totime) {
        $totime = time();
    }

    $params = ['userid' => $userid, 'fromtime' => $fromtime, 'totime' => $totime];
    $courseidsql = '';
    $courseids = array_filter(array_map('intval', (array) $courseids));
    if (!empty($courseids)) {
        [$coursessql, $courseparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');
        $courseidsql = " AND l.courseid $coursessql";
        $params = array_merge($params, $courseparams);
    }

    $sql = "SELECT *
              FROM {logstore_standard_log} l
             WHERE l.userid = :userid
               AND l.timecreated > :fromtime
               AND l.timecreated <= :totime
               $courseidsql
          ORDER BY l.timecreated ASC";

    $logentries = $DB->get_records_sql($sql, $params);
    $logcount = count($logentries);

    return $logentries;
}

/**
 * Format a duration in seconds as a human-readable string.
 *
 * @param int|null $duration
 * @param string|null $default
 * @return string
 */
function local_timespent_format_duration($duration, $default = null) {
    if ($duration == null) {
        if ($default) {
            return $default;
        }
        $duration = 0;
    }

    $dur = new stdClass();
    $dur->hours = floor($duration / 3600);
    $dur->minutes = floor(($duration % 3600) / 60);
    if ($dur->hours) {
        return get_string('duration_hh_mm', 'local_timespent', $dur);
    }
    return get_string('duration_mm', 'local_timespent', $dur);
}

/**
 * Format a unix timestamp for display.
 *
 * @param int $datetime
 * @return string
 */
function local_timespent_format_datetime($datetime) {
    global $CFG;

    if (!$datetime) {
        return get_string('never', 'local_timespent');
    }

    if ($CFG->debugdisplay && $CFG->debug >= DEBUG_DEVELOPER) {
        return userdate($datetime) . ' [' . $datetime . ']';
    } else if ($CFG->debugdisplay && $CFG->debug >= DEBUG_ALL) {
        return '<a title="' . $datetime . '">' . userdate($datetime) . '</a>';
    }
    return userdate($datetime);
}

/**
 * Table headers for the index report.
 *
 * @return array
 */
function local_timespent_report_index_header() {
    return [
        ['name' => '#', 'key' => '#', 'include' => false, 'show' => false],
        ['name' => get_string('name', 'local_timespent'), 'key' => 'name', 'include' => false, 'show' => false],
        [
            'name' => get_string('total_time_online', 'local_timespent'),
            'key' => 'total_time_online',
            'include' => false,
            'show' => false,
        ],
        [
            'name' => get_string('last_session_end', 'local_timespent'),
            'key' => 'last_session_end',
            'include' => false,
            'show' => false,
        ],
    ];
}

/**
 * Neutralise formula-triggering characters for spreadsheet export.
 *
 * @param string $data
 * @return string
 */
function local_timespent_clean_export_data($data) {
    $cleaneddata = str_replace('=', "'='", $data);
    $cleaneddata = str_replace('+', "'+'", $cleaneddata);
    $cleaneddata = str_replace('-', "'-'", $cleaneddata);
    $cleaneddata = str_replace('@', "'@'", $cleaneddata);
    return mb_convert_encoding($cleaneddata, 'UTF-8', 'UTF-8');
}

/**
 * Convert a duration (seconds or formatted string) into minutes.
 *
 * @param mixed $duration
 * @return float
 */
function local_timespent_duration_to_minutes($duration) {
    if (empty($duration)) {
        return 0;
    }

    if (is_numeric($duration)) {
        return round(((int) $duration) / 60, 2);
    }

    $minutes = 0;
    if (preg_match('/(\d+)\s*h/i', $duration, $match)) {
        $minutes += ((int) $match[1]) * 60;
    }
    if (preg_match('/(\d+)\s*m/i', $duration, $match)) {
        $minutes += (int) $match[1];
    }
    if (preg_match('/(\d+)\s*s/i', $duration, $match)) {
        $minutes += round(((int) $match[1]) / 60, 2);
    }

    return $minutes;
}
