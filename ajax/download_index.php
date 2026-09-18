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
 * @author     BitKea Technologies LLP
 * @copyright  2026 BitKea Technologies LLP
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/timespent/locallib.php');

require_login();
local_timespent_require_view_report();
require_sesskey();

global $DB;
$dataformat = required_param('dataformat', PARAM_ALPHA);
$searchdata = optional_param('searchdata', '', PARAM_TEXT);
$reportmode = optional_param('reportmode', 'course', PARAM_ALPHA);
$courseid = optional_param('courseid', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);

if (!in_array($dataformat, ['csv', 'excel'], true)) {
    throw new \moodle_exception('invalidparameter', 'error');
}
if (!in_array($reportmode, ['course', 'user'], true)) {
    $reportmode = 'course';
}

$filename = strtolower(get_string('pluginname', 'local_timespent') . '_' . date('dMY'));
$filename = str_replace(' ', '_', $filename);

$columns = [];
$rows = [];

if ($reportmode === 'user') {
    foreach (local_timespent_report_user_header() as $header) {
        if ($header['key'] === '#') {
            continue;
        }
        $columns[$header['key']] = $header['name'];
    }
    if ($userid > 0) {
        $report = local_timespent_get_report_user_courses($userid, $searchdata);
        $summaries = local_timespent_get_stored_course_summaries_for_user($userid, $report['courses']);
        foreach ($report['courses'] as $course) {
            $details = $summaries[(int) $course->id] ?? [
                'duration' => get_string('no_session', 'local_timespent'),
                'lastsessionlogout' => get_string('no_session', 'local_timespent'),
            ];
            $rows[] = [
                'course' => local_timespent_clean_export_data(format_string($course->fullname)),
                'total_time_online' => $details['duration'],
                'last_session_end' => strip_tags($details['lastsessionlogout']),
            ];
        }
    }
} else {
    foreach (local_timespent_report_index_header() as $header) {
        if ($header['key'] === '#') {
            continue;
        }
        $columns[$header['key']] = $header['name'];
    }
    if ($courseid && $courseid !== (int) SITEID) {
        $report = local_timespent_get_report_users($courseid, $searchdata);
        $summaries = local_timespent_get_stored_user_summaries_for_course($courseid, $report['users']);
        foreach ($report['users'] as $user) {
            $details = $summaries[(int) $user->id] ?? [
                'fullname' => fullname($user),
                'duration' => get_string('no_session', 'local_timespent'),
                'lastsessionlogout' => get_string('no_session', 'local_timespent'),
            ];
            $rows[] = [
                'name' => local_timespent_clean_export_data($details['fullname']),
                'total_time_online' => $details['duration'],
                'last_session_end' => strip_tags($details['lastsessionlogout']),
            ];
        }
    }
}

\core\dataformat::download_data($filename, $dataformat, $columns, $rows);
