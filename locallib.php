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

/** Quiet gap (seconds) that ends an online session. */
define('LOCAL_TIMESPENT_SESSION_TIMEOUT', 15 * 60);

/** Reuse stored aggregates for this many seconds before re-checking logs. */
define('LOCAL_TIMESPENT_RECALC_TTL', 300);

/** Max log rows read per batch from logstore_standard_log. */
define('LOCAL_TIMESPENT_LOG_BATCH', 1000);

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

    require_capability('local/timespent:viewreport', $context);
}

/**
 * Calculate and save sessions for a user in a course, then return summary data.
 *
 * Prefer incremental updates using local_timespent_progress. Avoids full
 * logstore scans on every report/theme request.
 *
 * $courseorregister is historically an object with ->id set to the course id
 * (column name in DB remains "register" for upgrade compatibility). Optional
 * ->offlinesessions enables offline session aggregation when present.
 *
 * @param stdClass $courseorregister Object with id (course id) and optional offlinesessions.
 * @param int $userid
 * @param int $fromtime Explicit start; 0 means use watermark / full rebuild as needed.
 * @param int|bool $formatted Return human-readable duration when true.
 * @param int $totime
 * @return array duration and lastsessionlogout keys
 */
function local_timespent_build_new_user_sessions($courseorregister, $userid, $fromtime = 0, $formatted = 0, $totime = 0) {
    global $DB;

    $courseid = (int) $courseorregister->id;
    $userid = (int) $userid;
    if (!$totime) {
        $totime = time();
    }

    $progress = local_timespent_get_progress($courseid, $userid);
    $useraggr = $DB->get_record('local_timespent_aggregate', [
        'register' => $courseid,
        'userid' => $userid,
        'grandtotal' => 1,
    ]);

    // Fast path: recent aggregate is good enough for display.
    if (
        $fromtime == 0
        && $useraggr
        && $progress
        && ((int) $progress->timemodified + LOCAL_TIMESPENT_RECALC_TTL) >= $totime
    ) {
        return local_timespent_format_summary_result($useraggr, $formatted);
    }

    $isfullrebuild = ($fromtime == 0 && !$progress && !$useraggr);
    if ($isfullrebuild) {
        local_timespent_delete_user_online_sessions($courseorregister, $userid);
        local_timespent_delete_user_aggregates($courseorregister, $userid);
        $fromtime = 0;
        $sessionstart = 0;
        $prevactivity = 0;
    } else if ($fromtime == 0 && !$progress && $useraggr) {
        // Existing installs: seed watermark from aggregate, only process newer logs.
        $fromtime = (int) ($useraggr->lastsessionlogout ?: 0);
        $sessionstart = 0;
        $prevactivity = $fromtime;
    } else if ($fromtime == 0 && $progress) {
        $fromtime = (int) $progress->lastlogtime;
        $sessionstart = (int) $progress->sessionstart;
        $prevactivity = (int) $progress->lastlogtime;
    } else {
        $sessionstart = $progress ? (int) $progress->sessionstart : 0;
        $prevactivity = $progress ? (int) $progress->lastlogtime : 0;
        if ($fromtime > 0) {
            local_timespent_delete_user_online_sessions($courseorregister, $userid, $fromtime);
        }
    }

    if ($progress && $fromtime > 0 && !$isfullrebuild) {
        // Drop incomplete tail sessions that overlap the window we will rebuild.
        local_timespent_delete_user_online_sessions($courseorregister, $userid, $fromtime);
    }

    $newsessionscount = local_timespent_process_log_batches(
        $courseorregister,
        $userid,
        $fromtime,
        $totime,
        $sessionstart,
        $prevactivity
    );

    // Close an open session that has gone idle.
    if ($sessionstart && $prevactivity && (($totime - $prevactivity) > LOCAL_TIMESPENT_SESSION_TIMEOUT)) {
        $estimatedsessionend = $prevactivity + (int) (LOCAL_TIMESPENT_SESSION_TIMEOUT / 2);
        local_timespent_save_session($courseorregister, $userid, $sessionstart, $estimatedsessionend);
        $newsessionscount++;
        $sessionstart = 0;
    }

    if ($newsessionscount || $isfullrebuild || !$useraggr) {
        local_timespent_update_user_aggregates($courseorregister, $userid);
    }

    local_timespent_save_progress($courseid, $userid, $prevactivity, $sessionstart, $totime);

    $useraggr = $DB->get_record('local_timespent_aggregate', [
        'register' => $courseid,
        'userid' => $userid,
        'grandtotal' => 1,
    ]);

    return local_timespent_format_summary_result($useraggr, $formatted);
}

/**
 * Process logstore rows in small batches and update session state by reference.
 *
 * @param stdClass $courseorregister
 * @param int $userid
 * @param int $fromtime
 * @param int $totime
 * @param int $sessionstart
 * @param int $prevactivity
 * @return int Number of sessions saved
 */
function local_timespent_process_log_batches(
    $courseorregister,
    int $userid,
    int $fromtime,
    int $totime,
    int &$sessionstart,
    int &$prevactivity
): int {
    $newsessionscount = 0;
    $lastid = 0;

    do {
        $batch = local_timespent_get_user_log_entries_batch(
            $userid,
            $fromtime,
            [(int) $courseorregister->id],
            $totime,
            $lastid,
            LOCAL_TIMESPENT_LOG_BATCH
        );
        $batchcount = count($batch);
        if (!$batchcount) {
            break;
        }

        foreach ($batch as $logentry) {
            $lastid = (int) $logentry->id;
            $activitytime = (int) $logentry->timecreated;

            if (!$sessionstart) {
                $sessionstart = $activitytime;
                $prevactivity = $activitytime;
                continue;
            }

            if (($activitytime - $prevactivity) > LOCAL_TIMESPENT_SESSION_TIMEOUT) {
                $estimatedsessionend = $prevactivity + (int) (LOCAL_TIMESPENT_SESSION_TIMEOUT / 2);
                local_timespent_save_session($courseorregister, $userid, $sessionstart, $estimatedsessionend);
                $newsessionscount++;
                $sessionstart = $activitytime;
            }
            $prevactivity = $activitytime;
        }
    } while ($batchcount === LOCAL_TIMESPENT_LOG_BATCH);

    return $newsessionscount;
}

/**
 * Format aggregate row into API summary.
 *
 * @param stdClass|false|null $useraggr
 * @param int|bool $formatted
 * @return array
 */
function local_timespent_format_summary_result($useraggr, $formatted = 0): array {
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
 * Record a live course activity without scanning logstore.
 *
 * Used by event observers so totals stay current with small writes.
 *
 * @param int $userid
 * @param int $courseid
 * @param int $activitytime
 * @return void
 */
function local_timespent_record_activity_event(int $userid, int $courseid, int $activitytime): void {
    if ($userid <= 0 || $courseid <= 0 || $courseid === (int) SITEID || isguestuser($userid)) {
        return;
    }

    $courseorregister = (object) ['id' => $courseid];
    $progress = local_timespent_get_progress($courseid, $userid);
    $sessionstart = $progress ? (int) $progress->sessionstart : 0;
    $prevactivity = $progress ? (int) $progress->lastlogtime : 0;
    $newsessions = 0;

    if (!$sessionstart) {
        $sessionstart = $activitytime;
    } else if ($prevactivity && (($activitytime - $prevactivity) > LOCAL_TIMESPENT_SESSION_TIMEOUT)) {
        $estimatedsessionend = $prevactivity + (int) (LOCAL_TIMESPENT_SESSION_TIMEOUT / 2);
        local_timespent_save_session($courseorregister, $userid, $sessionstart, $estimatedsessionend);
        $newsessions++;
        $sessionstart = $activitytime;
    }

    $prevactivity = max($prevactivity, $activitytime);
    local_timespent_save_progress($courseid, $userid, $prevactivity, $sessionstart, time());

    if ($newsessions) {
        local_timespent_update_user_aggregates($courseorregister, $userid);
    }
}

/**
 * Get progress watermark row.
 *
 * @param int $courseid
 * @param int $userid
 * @return stdClass|false
 */
function local_timespent_get_progress(int $courseid, int $userid) {
    global $DB;
    return $DB->get_record('local_timespent_progress', [
        'register' => $courseid,
        'userid' => $userid,
    ]);
}

/**
 * Upsert progress watermark.
 *
 * @param int $courseid
 * @param int $userid
 * @param int $lastlogtime
 * @param int $sessionstart
 * @param int $timemodified
 * @return void
 */
function local_timespent_save_progress(
    int $courseid,
    int $userid,
    int $lastlogtime,
    int $sessionstart,
    int $timemodified
): void {
    global $DB;

    $existing = local_timespent_get_progress($courseid, $userid);
    if ($existing) {
        $existing->lastlogtime = $lastlogtime;
        $existing->sessionstart = $sessionstart;
        $existing->timemodified = $timemodified;
        $DB->update_record('local_timespent_progress', $existing);
        return;
    }

    $record = (object) [
        'register' => $courseid,
        'userid' => $userid,
        'lastlogtime' => $lastlogtime,
        'sessionstart' => $sessionstart,
        'timemodified' => $timemodified,
    ];
    $DB->insert_record('local_timespent_progress', $record);
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
 * Timestamp of the oldest course log entry for a user in a course.
 *
 * @param int $userid
 * @param int $courseid
 * @return int|null
 * @deprecated since 1.2.0 — kept for compatibility; prefer progress watermark.
 */
function local_timespent_get_user_oldest_log_entry_timestamp($userid, $courseid = 0) {
    global $DB;

    $params = ['userid' => $userid];
    $coursesql = '';
    if ($courseid) {
        $coursesql = ' AND courseid = :courseid';
        $params['courseid'] = $courseid;
    }

    $obj = $DB->get_record_sql(
        'SELECT MIN(timecreated) as oldestlogtime
           FROM {logstore_standard_log}
          WHERE userid = :userid' . $coursesql,
        $params,
        IGNORE_MISSING
    );
    if ($obj && $obj->oldestlogtime) {
        return (int) $obj->oldestlogtime;
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
 * Kept for compatibility. Prefer local_timespent_get_user_log_entries_batch().
 *
 * @param int $userid
 * @param int $fromtime
 * @param array $courseids
 * @param int $logcount Count of records, passed by reference.
 * @param int $totime
 * @return array
 */
function local_timespent_get_user_log_entries_in_courses($userid, $fromtime, $courseids, &$logcount, $totime = 0) {
    $entries = [];
    $lastid = 0;
    $logcount = 0;
    if (!$totime) {
        $totime = time();
    }

    do {
        $batch = local_timespent_get_user_log_entries_batch(
            $userid,
            $fromtime,
            $courseids,
            $totime,
            $lastid,
            LOCAL_TIMESPENT_LOG_BATCH
        );
        $batchcount = count($batch);
        if (!$batchcount) {
            break;
        }
        foreach ($batch as $entry) {
            $entries[$entry->id] = $entry;
            $lastid = (int) $entry->id;
        }
        $logcount += $batchcount;
    } while ($batchcount === LOCAL_TIMESPENT_LOG_BATCH);

    return $entries;
}

/**
 * Fetch one limited batch of log rows for a user in courses.
 *
 * Selects only the fields required for session calculation.
 *
 * @param int $userid
 * @param int $fromtime
 * @param array $courseids Required — empty returns no rows.
 * @param int $totime
 * @param int $afterid Continue after this log id (for stable batching).
 * @param int $limit
 * @return array
 */
function local_timespent_get_user_log_entries_batch(
    int $userid,
    int $fromtime,
    array $courseids,
    int $totime,
    int $afterid = 0,
    int $limit = LOCAL_TIMESPENT_LOG_BATCH
): array {
    global $DB;

    $courseids = array_values(array_filter(array_map('intval', $courseids)));
    if (empty($courseids)) {
        // Never scan logstore without a course restriction.
        return [];
    }

    if (!$fromtime) {
        $fromtime = 0;
    }
    if (!$totime) {
        $totime = time();
    }
    if ($limit < 1) {
        $limit = LOCAL_TIMESPENT_LOG_BATCH;
    }

    [$coursessql, $courseparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');
    $params = array_merge([
        'userid' => $userid,
        'fromtime' => $fromtime,
        'totime' => $totime,
        'afterid' => $afterid,
    ], $courseparams);

    $sql = "SELECT l.id, l.timecreated, l.courseid, l.userid
              FROM {logstore_standard_log} l
             WHERE l.userid = :userid
               AND l.courseid $coursessql
               AND l.timecreated > :fromtime
               AND l.timecreated <= :totime
               AND l.id > :afterid
          ORDER BY l.timecreated ASC, l.id ASC";

    return $DB->get_records_sql($sql, $params, 0, $limit);
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
        ['name' => '#', 'key' => '#'],
        ['name' => get_string('name', 'local_timespent'), 'key' => 'name'],
        ['name' => get_string('total_time_online', 'local_timespent'), 'key' => 'total_time_online'],
        ['name' => get_string('last_session_end', 'local_timespent'), 'key' => 'last_session_end'],
    ];
}

/**
 * Enrolled users for the time spent report, with optional name search.
 *
 * @param int $courseid
 * @param string $searchdata
 * @param int $limitfrom
 * @param int $limitnum Zero means no limit.
 * @return array{users: array, total: int}
 */
function local_timespent_get_report_users(
    int $courseid,
    string $searchdata = '',
    int $limitfrom = 0,
    int $limitnum = 0
): array {
    global $DB;

    $coursecontext = \context_course::instance($courseid);
    [$esql, $params] = get_enrolled_sql($coursecontext);

    $where = 'u.deleted = 0';
    if ($searchdata !== '') {
        $likesql = $DB->sql_like('u.firstname', ':search1', false)
            . ' OR ' . $DB->sql_like('u.lastname', ':search2', false)
            . ' OR ' . $DB->sql_like('u.email', ':search3', false)
            . ' OR ' . $DB->sql_like('u.username', ':search4', false);
        $where .= " AND ($likesql)";
        $params['search1'] = '%' . $DB->sql_like_escape($searchdata) . '%';
        $params['search2'] = $params['search1'];
        $params['search3'] = $params['search1'];
        $params['search4'] = $params['search1'];
    }

    $sqlcount = "SELECT COUNT(u.id)
                   FROM {user} u
                   JOIN ($esql) je ON je.id = u.id
                  WHERE $where";
    $total = (int) $DB->count_records_sql($sqlcount, $params);

    $usernamefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false);
    [$sort, $sortparams] = users_order_by_sql('u');
    $params = array_merge($params, $sortparams);

    $sql = "SELECT u.id, {$usernamefields->selects}
              FROM {user} u
              JOIN ($esql) je ON je.id = u.id
             WHERE $where
          ORDER BY $sort";

    if ($limitnum) {
        $users = $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);
    } else {
        $users = $DB->get_records_sql($sql, $params);
    }

    return ['users' => $users, 'total' => $total];
}

/**
 * Session totals for one user in a course.
 *
 * @param int $courseid
 * @param \stdClass $user
 * @return array{fullname: string, duration: string, lastsessionlogout: string}
 */
function local_timespent_prepare_user_report_data(int $courseid, \stdClass $user): array {
    $details = local_timespent_build_new_user_sessions((object) ['id' => $courseid], $user->id, 0, true);
    return [
        'fullname' => fullname($user),
        'duration' => $details['duration'],
        'lastsessionlogout' => $details['lastsessionlogout'],
    ];
}

/**
 * Build paginated report payload for external services / AJAX.
 *
 * @param int $courseid
 * @param string $searchdata
 * @param int $page 1-based page number
 * @param int $perpage
 * @return array
 */
function local_timespent_get_index_report_data(
    int $courseid,
    string $searchdata = '',
    int $page = 1,
    int $perpage = 10
): array {
    if (!in_array($perpage, [10, 25, 50, 100], true)) {
        $perpage = 10;
    }
    $page = max(1, $page);
    $start = ($page - 1) * $perpage;
    $rows = [];

    if (!$courseid || $courseid === (int) SITEID) {
        return [
            'reports' => [],
            'total' => 0,
            'strarfrom' => 0,
            'limitto' => 0,
        ];
    }

    $report = local_timespent_get_report_users($courseid, $searchdata, $start, $perpage);
    $i = $report['total'] ? ($start + 1) : 0;
    foreach ($report['users'] as $user) {
        $details = local_timespent_prepare_user_report_data($courseid, $user);
        $profileurl = (new moodle_url('/user/profile.php', ['id' => $user->id]))->out(false);
        $rows[] = [
            'rownumber' => $i,
            'userid' => (int) $user->id,
            'fullname' => $details['fullname'],
            'profileurl' => $profileurl,
            'duration' => $details['duration'],
            'lastsessionlogout' => strip_tags($details['lastsessionlogout']),
        ];
        $i++;
    }

    $limitto = min($start + $perpage, $report['total']);
    $strarfrom = $report['total'] ? ($start + 1) : 0;

    return [
        'reports' => $rows,
        'total' => $report['total'],
        'strarfrom' => $strarfrom,
        'limitto' => $limitto,
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
