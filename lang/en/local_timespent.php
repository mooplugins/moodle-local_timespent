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
 * @author     BitKea Technologies LLP
 * @copyright  2026 BitKea Technologies LLP
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['clearselection'] = 'Clear selection';
$string['duration_hh_mm'] = '{$a->hours} h, {$a->minutes} min';
$string['duration_mm'] = '{$a->minutes} min';
$string['export'] = 'Export';
$string['exportcsv'] = 'CSV';
$string['exportexceedsmax'] = 'Export exceeds the maximum of {$a} rows. Narrow the search or export in smaller batches.';
$string['exportexcel'] = 'Excel';
$string['getreport'] = 'Get report';
$string['last_session_end'] = 'Last session end';
$string['loading'] = 'Loading...';
$string['name'] = 'Name';
$string['never'] = '(never)';
$string['next'] = 'Next';
$string['no_session'] = 'No session';
$string['nocourses'] = 'No courses available.';
$string['nocoursesfound'] = 'No courses found';
$string['nodataavailable'] = 'No data available in table';
$string['nousers'] = 'No enrolled users found for this course.';
$string['nousersfound'] = 'No users found';
$string['pluginname'] = 'Time spent';
$string['previous'] = 'Previous';
$string['privacy:metadata:local_timespent_aggregate'] = 'Stores aggregated time-spent totals for users in courses.';
$string['privacy:metadata:local_timespent_aggregate:duration'] = 'Total duration in seconds.';
$string['privacy:metadata:local_timespent_aggregate:grandtotal'] = 'Whether this row is the grand total for the user in the course.';
$string['privacy:metadata:local_timespent_aggregate:lastsessionlogout'] = 'End of the last calculated online session.';
$string['privacy:metadata:local_timespent_aggregate:onlinesess'] = 'Whether the aggregate is for online sessions.';
$string['privacy:metadata:local_timespent_aggregate:refcourse'] = 'Optional related course id for the aggregate.';
$string['privacy:metadata:local_timespent_aggregate:register'] = 'The course the aggregate belongs to.';
$string['privacy:metadata:local_timespent_aggregate:total'] = 'Whether this row is a total aggregate.';
$string['privacy:metadata:local_timespent_aggregate:userid'] = 'The user the aggregate belongs to.';
$string['privacy:metadata:local_timespent_progress'] = 'Stores per-user processing watermarks for incremental time-spent calculation.';
$string['privacy:metadata:local_timespent_progress:lastlogtime'] = 'Timestamp of the last processed activity.';
$string['privacy:metadata:local_timespent_progress:register'] = 'The course the progress record belongs to.';
$string['privacy:metadata:local_timespent_progress:sessionstart'] = 'Start of the current open online session.';
$string['privacy:metadata:local_timespent_progress:timemodified'] = 'When the progress record was last updated.';
$string['privacy:metadata:local_timespent_progress:userid'] = 'The user the progress record belongs to.';
$string['privacy:metadata:local_timespent_session'] = 'Stores calculated online sessions for users in courses.';
$string['privacy:metadata:local_timespent_session:addedbyuserid'] = 'User who manually added the session, if any.';
$string['privacy:metadata:local_timespent_session:comments'] = 'Optional comment text attached to the session.';
$string['privacy:metadata:local_timespent_session:duration'] = 'Session duration in seconds.';
$string['privacy:metadata:local_timespent_session:login'] = 'Session start timestamp.';
$string['privacy:metadata:local_timespent_session:logout'] = 'Session end timestamp.';
$string['privacy:metadata:local_timespent_session:onlinesess'] = 'Whether the session was calculated from online activity.';
$string['privacy:metadata:local_timespent_session:refcourse'] = 'Optional related course id for the session.';
$string['privacy:metadata:local_timespent_session:register'] = 'The course the session belongs to.';
$string['privacy:metadata:local_timespent_session:userid'] = 'The user the session belongs to.';
$string['recordsperpage'] = 'Records per page';
$string['reportmode'] = 'Report type';
$string['reportmode_course'] = 'By course';
$string['reportmode_user'] = 'By user';
$string['search'] = 'Go';
$string['searchcourseplaceholder'] = 'Search courses';
$string['searchplaceholder'] = 'Search a record';
$string['searchuserplaceholder'] = 'Search users';
$string['select_course'] = 'Select course';
$string['select_user'] = 'Select user';
$string['selectcourseprompt'] = 'Search and select a course to load the report.';
$string['selectuserprompt'] = 'Search and select a user to load their courses.';
$string['showingrecords'] = 'Showing {$a->from} - {$a->to} of {$a->total}';
$string['timespent:viewreport'] = 'View time spent report (site-wide access to any course or user)';
$string['timespent_report'] = 'Time spent report';
$string['timespent_report_shortdesc'] = 'This report shows time spent by course or by user across their enrolled courses. Access is site-wide: grant only to trusted roles.';
$string['title'] = 'Time spent';
$string['total_time_online'] = 'Total time online';
