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
 * Language strings for local_timespent.
 *
 * @package    local_timespent
 * @copyright  2026 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['duration_hh_mm'] = '{$a->hours} h, {$a->minutes} min';
$string['duration_mm'] = '{$a->minutes} min';
$string['export'] = 'Export';
$string['exportcsv'] = 'CSV';
$string['exportexcel'] = 'Excel';
$string['last_session_end'] = 'Last session end';
$string['loading'] = 'Loading...';
$string['name'] = 'Name';
$string['never'] = '(never)';
$string['next'] = 'Next';
$string['no_session'] = 'No session';
$string['nocourses'] = 'No courses available.';
$string['nodataavailable'] = 'No data available in table';
$string['nousers'] = 'No enrolled users found for this course.';
$string['pluginname'] = 'Time spent';
$string['previous'] = 'Previous';
$string['privacy:metadata:local_timespent_aggregate'] = 'Stores aggregated time-spent totals for users in courses.';
$string['privacy:metadata:local_timespent_aggregate:duration'] = 'Total duration in seconds.';
$string['privacy:metadata:local_timespent_aggregate:lastsessionlogout'] = 'End of the last calculated online session.';
$string['privacy:metadata:local_timespent_aggregate:register'] = 'The course the aggregate belongs to.';
$string['privacy:metadata:local_timespent_aggregate:userid'] = 'The user the aggregate belongs to.';
$string['privacy:metadata:local_timespent_session'] = 'Stores calculated online sessions for users in courses.';
$string['privacy:metadata:local_timespent_session:duration'] = 'Session duration in seconds.';
$string['privacy:metadata:local_timespent_session:login'] = 'Session start timestamp.';
$string['privacy:metadata:local_timespent_session:logout'] = 'Session end timestamp.';
$string['privacy:metadata:local_timespent_session:register'] = 'The course the session belongs to.';
$string['privacy:metadata:local_timespent_session:userid'] = 'The user the session belongs to.';
$string['recordsperpage'] = 'Records per page';
$string['search'] = 'Go';
$string['searchplaceholder'] = 'Search a record';
$string['select_course'] = 'Select course';
$string['selectcourseprompt'] = 'Select a course to load the report.';
$string['showingrecords'] = 'Showing {$a->from} - {$a->to} of {$a->total}';
$string['timespent:viewreport'] = 'View time spent report';
$string['timespent_report'] = 'Time spent report';
$string['timespent_report_shortdesc'] = 'This report shows time spent by enrolled users in a selected course.';
$string['title'] = 'Time spent';
$string['total_time_online'] = 'Total time online';
