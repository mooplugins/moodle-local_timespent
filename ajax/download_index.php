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
        $rows = local_timespent_export_user_report_rows($userid, $searchdata);
    }
} else {
    foreach (local_timespent_report_index_header() as $header) {
        if ($header['key'] === '#') {
            continue;
        }
        $columns[$header['key']] = $header['name'];
    }
    if ($courseid && $courseid !== (int) SITEID) {
        $rows = local_timespent_export_course_report_rows($courseid, $searchdata);
    }
}

\core\dataformat::download_data($filename, $dataformat, $columns, $rows);
