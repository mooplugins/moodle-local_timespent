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
 * Time spent report page.
 *
 * @package    local_timespent
 * @author     BitKea Technologies LLP
 * @copyright  2026 BitKea Technologies LLP
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/local/timespent/locallib.php');

admin_externalpage_setup('local_timespent_report', '', null, '', ['pagelayout' => 'report']);
$PAGE->set_primary_active_tab('siteadminnode');

$heading = get_string('timespent_report', 'local_timespent');
$PAGE->set_heading($heading);
$PAGE->set_title($heading);
$PAGE->add_body_class('limitedwidth');

$tableheader = local_timespent_report_index_header();
$usertableheader = local_timespent_report_user_header();
$loadinggifurl = $OUTPUT->image_url('i/loading', 'core')->out(false);
$showingrecordsformat = get_string('showingrecords', 'local_timespent', (object) [
    'from' => '%%FROM%%',
    'to' => '%%TO%%',
    'total' => '%%TOTAL%%',
]);

$downloadurl = (new moodle_url('/local/timespent/ajax/download_index.php'))->out(false);

$headerjson = array_map(static function ($h) {
    return ['name' => $h['name']];
}, $tableheader);
$userheaderjson = array_map(static function ($h) {
    return ['name' => $h['name']];
}, $usertableheader);
$jsonflags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

$data = [
    'downloadajaxurl' => $downloadurl,
    'currentuserid' => $USER->id,
    'loadinggif' => $loadinggifurl,
    'tableheader' => $tableheader,
    'usertableheader' => $usertableheader,
    'tableheaderjson' => json_encode($headerjson, $jsonflags),
    'usertableheaderjson' => json_encode($userheaderjson, $jsonflags),
    'sesskey' => sesskey(),
    'search_placeholder' => get_string('searchplaceholder', 'local_timespent'),
    'searchuser_placeholder' => get_string('searchuserplaceholder', 'local_timespent'),
    'searchcourse_placeholder' => get_string('searchcourseplaceholder', 'local_timespent'),
    'search_go_label' => get_string('search', 'local_timespent'),
    'exportlabel' => get_string('export', 'local_timespent'),
    'exportcsv' => get_string('exportcsv', 'local_timespent'),
    'exportexcel' => get_string('exportexcel', 'local_timespent'),
    'recordsperpage' => get_string('recordsperpage', 'local_timespent'),
    'previouslabel' => get_string('previous', 'local_timespent'),
    'nextlabel' => get_string('next', 'local_timespent'),
    'selectcourseprompt' => get_string('selectcourseprompt', 'local_timespent'),
    'selectuserprompt' => get_string('selectuserprompt', 'local_timespent'),
    'nodataavailable' => get_string('nodataavailable', 'local_timespent'),
    'nousersfound' => get_string('nousersfound', 'local_timespent'),
    'nocoursesfound' => get_string('nocoursesfound', 'local_timespent'),
    'showingrecordsformat' => $showingrecordsformat,
    'colcount' => count($tableheader),
    'reportmode_course' => get_string('reportmode_course', 'local_timespent'),
    'reportmode_user' => get_string('reportmode_user', 'local_timespent'),
    'selectuserlabel' => get_string('select_user', 'local_timespent'),
    'selectcourselabel' => get_string('select_course', 'local_timespent'),
    'clearselection' => get_string('clearselection', 'local_timespent'),
];

$PAGE->requires->js_call_amd('local_timespent/report', 'init', [[
    'downloadurl' => $downloadurl,
    'sesskey' => sesskey(),
    'nodata' => get_string('nodataavailable', 'local_timespent'),
    'showingrecords' => $showingrecordsformat,
    'colcount' => count($tableheader),
    'selectcourseprompt' => get_string('selectcourseprompt', 'local_timespent'),
    'selectuserprompt' => get_string('selectuserprompt', 'local_timespent'),
    'searchRecord' => get_string('searchplaceholder', 'local_timespent'),
    'searchUser' => get_string('searchuserplaceholder', 'local_timespent'),
    'searchCourse' => get_string('searchcourseplaceholder', 'local_timespent'),
    'selectuserlabel' => get_string('select_user', 'local_timespent'),
    'selectcourselabel' => get_string('select_course', 'local_timespent'),
    'nousersfound' => get_string('nousersfound', 'local_timespent'),
    'nocoursesfound' => get_string('nocoursesfound', 'local_timespent'),
    'clearselection' => get_string('clearselection', 'local_timespent'),
]]);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_timespent/report_index', $data);
echo $OUTPUT->footer();
