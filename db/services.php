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
 * External service definitions for local_timespent.
 *
 * @package    local_timespent
 * @author     BitKea Technologies LLP
 * @copyright  2026 BitKea Technologies LLP
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_timespent_get_index_report' => [
        'classname' => 'local_timespent\external\get_index_report',
        'methodname' => 'execute',
        'description' => 'Get paginated time spent report rows for a course',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
        'capabilities' => 'local/timespent:viewreport',
    ],
    'local_timespent_get_user_report' => [
        'classname' => 'local_timespent\external\get_user_report',
        'methodname' => 'execute',
        'description' => 'Get paginated time spent report rows for one user across courses',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
        'capabilities' => 'local/timespent:viewreport',
    ],
    'local_timespent_search_users' => [
        'classname' => 'local_timespent\external\search_users',
        'methodname' => 'execute',
        'description' => 'Search users for the time spent user report picker',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
        'capabilities' => 'local/timespent:viewreport',
    ],
    'local_timespent_search_courses' => [
        'classname' => 'local_timespent\external\search_courses',
        'methodname' => 'execute',
        'description' => 'Search courses for the time spent course report picker',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
        'capabilities' => 'local/timespent:viewreport',
    ],
];
