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
 * External API: get time spent report rows for one user across courses.
 *
 * @package    local_timespent
 * @author     BitKea Technologies LLP
 * @copyright  2026 BitKea Technologies LLP
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_timespent\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../locallib.php');

/**
 * Get paginated time spent rows for one user's courses.
 */
class get_user_report extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'User id'),
            'page' => new external_value(PARAM_INT, '1-based page number', VALUE_DEFAULT, 1),
            'perpage' => new external_value(PARAM_INT, 'Rows per page', VALUE_DEFAULT, 10),
            'searchdata' => new external_value(PARAM_TEXT, 'Optional course name search', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $userid
     * @param int $page
     * @param int $perpage
     * @param string $searchdata
     * @return array
     */
    public static function execute(int $userid, int $page = 1, int $perpage = 10, string $searchdata = ''): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'userid' => $userid,
            'page' => $page,
            'perpage' => $perpage,
            'searchdata' => $searchdata,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        local_timespent_require_view_report($context);

        return local_timespent_get_user_report_data(
            $params['userid'],
            $params['searchdata'],
            $params['page'],
            $params['perpage']
        );
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'reports' => new external_multiple_structure(
                new external_single_structure([
                    'rownumber' => new external_value(PARAM_INT, 'Row number'),
                    'courseid' => new external_value(PARAM_INT, 'Course id'),
                    'coursename' => new external_value(PARAM_TEXT, 'Course full name'),
                    'courseurl' => new external_value(PARAM_URL, 'Course URL'),
                    'duration' => new external_value(PARAM_TEXT, 'Formatted duration'),
                    'lastsessionlogout' => new external_value(PARAM_TEXT, 'Last session end'),
                ])
            ),
            'total' => new external_value(PARAM_INT, 'Total matching courses'),
            'strarfrom' => new external_value(PARAM_INT, 'First row index shown'),
            'limitto' => new external_value(PARAM_INT, 'Last row index shown'),
        ]);
    }
}
