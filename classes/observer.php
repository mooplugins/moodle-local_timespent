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
 * Event observer callbacks for local_timespent.
 *
 * @package    local_timespent
 * @author     BitKea Technologies LLP
 * @copyright  2026 BitKea Technologies LLP
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_timespent;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../locallib.php');

/**
 * Observe course activity to keep time-spent totals current.
 */
class observer {
    /**
     * Course viewed.
     *
     * @param \core\event\course_viewed $event
     * @return void
     */
    public static function course_viewed(\core\event\course_viewed $event): void {
        local_timespent_record_activity_event(
            (int) $event->userid,
            (int) $event->courseid,
            (int) $event->timecreated
        );
    }

    /**
     * Course module viewed.
     *
     * @param \core\event\course_module_viewed $event
     * @return void
     */
    public static function course_module_viewed(\core\event\course_module_viewed $event): void {
        local_timespent_record_activity_event(
            (int) $event->userid,
            (int) $event->courseid,
            (int) $event->timecreated
        );
    }

    /**
     * User logged out — close any session still open.
     *
     * @param \core\event\user_loggedout $event
     * @return void
     */
    public static function user_loggedout(\core\event\user_loggedout $event): void {
        local_timespent_close_user_open_sessions(
            (int) $event->objectid,
            (int) $event->timecreated
        );
    }
}
