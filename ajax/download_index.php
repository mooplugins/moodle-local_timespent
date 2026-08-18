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
 * Download time spent report.
 *
 * @package    local_timespent
 * @copyright  2026 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/timespent/locallib.php');

require_login();
local_timespent_require_view_report();
require_sesskey();

$dataformat = required_param('dataformat', PARAM_ALPHA);
$searchdata = optional_param('searchdata', '', PARAM_TEXT);
$courseid = required_param('courseid', PARAM_INT);

$tableheader = local_timespent_report_index_header();
unset($tableheader[0]); // Skip row number column in export.

$columns = [];
foreach ($tableheader as $header) {
    $columns[$header['key']] = $header['name'];
}

$filename = strtolower(get_string('pluginname', 'local_timespent') . '_' . date('dMY'));
$filename = str_replace(' ', '_', $filename);

$rows = [];
if ($courseid && $courseid !== (int) SITEID) {
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

    $reports = $DB->get_records_sql($sql, $params);
    foreach ($reports as $report) {
        $details = local_timespent_build_new_user_sessions($courseorregister, $report->id, 0, true);
        $rows[] = [
            'name' => local_timespent_clean_export_data(fullname($report)),
            'total_time_online' => $details['duration'],
            'last_session_end' => strip_tags($details['lastsessionlogout']),
        ];
    }
}

\core\dataformat::download_data($filename, $dataformat, $columns, $rows);
