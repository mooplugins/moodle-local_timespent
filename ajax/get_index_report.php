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
 * Legacy AJAX endpoint (prefer local_timespent_get_index_report external service).
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
if (optional_param('sesskey', '', PARAM_RAW) !== '') {
    require_sesskey();
}

$courseid = optional_param('courseid', 0, PARAM_INT);
$page = max(1, optional_param('currentpagenumber', 1, PARAM_INT));
$limit = optional_param('rec_per_page', 10, PARAM_INT);
$searchdata = optional_param('searchdata', '', PARAM_TEXT);

$data = local_timespent_get_index_report_data($courseid, $searchdata, $page, $limit);

// Preserve previous HTML cell format for any remaining legacy callers.
$htmlrows = [];
foreach ($data['reports'] as $row) {
    $htmlrows[] = [
        $row['rownumber'],
        html_writer::link($row['profileurl'], $row['fullname']),
        $row['duration'],
        $row['lastsessionlogout'],
    ];
}

echo json_encode([
    'reports' => $htmlrows,
    'total' => $data['total'],
    'strarfrom' => $data['strarfrom'],
    'limitto' => $data['limitto'],
]);
