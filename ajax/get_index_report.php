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
 * AJAX endpoint: paginated time spent report rows.
 *
 * @package    local_timespent
 * @copyright  2026 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/timespent/locallib.php');

require_login();
local_timespent_require_view_report();
// Sesskey is preferred; do not hard-fail if omitted (report is already capability-gated).
if (optional_param('sesskey', '', PARAM_RAW) !== '') {
    require_sesskey();
}

$courseid = optional_param('courseid', 0, PARAM_INT);
$page = max(0, optional_param('currentpagenumber', 1, PARAM_INT) - 1);
$limit = optional_param('rec_per_page', 10, PARAM_INT);
$searchdata = optional_param('searchdata', '', PARAM_TEXT);

if (!in_array($limit, [10, 25, 50, 100], true)) {
    $limit = 10;
}

$start = $page * $limit;
$rows = [];

if (!$courseid || $courseid === (int) SITEID) {
    echo json_encode([
        'reports' => [],
        'total' => 0,
        'strarfrom' => 0,
        'limitto' => 0,
    ]);
    die();
}

$courseorregister = (object) ['id' => $courseid];
$coursecontext = context_course::instance($courseid);
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

$sql = "SELECT u.id, u.firstname, u.lastname
          FROM {user} u
          JOIN ($esql) je ON je.id = u.id
         WHERE $where
      ORDER BY u.firstname ASC";

$sqlcount = "SELECT COUNT(u.id)
               FROM {user} u
               JOIN ($esql) je ON je.id = u.id
              WHERE $where";

$reportscount = (int) $DB->count_records_sql($sqlcount, $params);
$reports = $DB->get_records_sql($sql, $params, $start, $limit);

$i = $reportscount ? ($start + 1) : 0;
foreach ($reports as $report) {
    $details = local_timespent_build_new_user_sessions($courseorregister, $report->id, 0, true);
    $fullname = fullname($report);
    $profileurl = (new moodle_url('/user/profile.php', ['id' => $report->id]))->out(false);
    $rows[] = [
        $i,
        html_writer::link($profileurl, s(local_timespent_clean_export_data($fullname))),
        s($details['duration']),
        $details['lastsessionlogout'],
    ];
    $i++;
}

$limitto = min($start + $limit, $reportscount);
$strarfrom = $reportscount ? ($start + 1) : 0;

echo json_encode([
    'reports' => $rows,
    'total' => $reportscount,
    'strarfrom' => $strarfrom,
    'limitto' => $limitto,
]);
