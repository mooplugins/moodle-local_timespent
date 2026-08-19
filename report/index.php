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
 * @copyright  2026 Mooplugins
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

$reportjs = '/local/timespent/js/report/index.js';
$PAGE->requires->js(new moodle_url($reportjs, ['v' => filemtime($CFG->dirroot . $reportjs)]), true);

$courses = [
    ['id' => 0, 'fullname' => get_string('select_course', 'local_timespent')],
];
foreach (get_courses() as $course) {
    if ((int) $course->id === (int) SITEID || (int) $course->category === 0) {
        continue;
    }
    $courses[] = [
        'id' => (int) $course->id,
        'fullname' => format_string($course->fullname),
    ];
}
usort($courses, static function ($a, $b) {
    if ((int) $a['id'] === 0) {
        return -1;
    }
    if ((int) $b['id'] === 0) {
        return 1;
    }
    return strcasecmp($a['fullname'], $b['fullname']);
});

$tableheader = local_timespent_report_index_header();
$loadinggifurl = $OUTPUT->image_url('i/loading', 'core')->out(false);
$showingrecordsformat = get_string('showingrecords', 'local_timespent', (object) [
    'from' => '%%FROM%%',
    'to' => '%%TO%%',
    'total' => '%%TOTAL%%',
]);

$data = [
    'ajaxUrl' => (new moodle_url('/local/timespent/ajax/get_index_report.php'))->out(false),
    'downloadajaxurl' => (new moodle_url('/local/timespent/ajax/download_index.php'))->out(false),
    'currentuserid' => $USER->id,
    'loadinggif' => $loadinggifurl,
    'courses' => $courses,
    'tableheader' => $tableheader,
    'sesskey' => sesskey(),
    'search_placeholder' => get_string('searchplaceholder', 'local_timespent'),
    'search_go_label' => get_string('search', 'local_timespent'),
    'exportlabel' => get_string('export', 'local_timespent'),
    'exportcsv' => get_string('exportcsv', 'local_timespent'),
    'exportexcel' => get_string('exportexcel', 'local_timespent'),
    'recordsperpage' => get_string('recordsperpage', 'local_timespent'),
    'previouslabel' => get_string('previous', 'local_timespent'),
    'nextlabel' => get_string('next', 'local_timespent'),
    'selectcourseprompt' => get_string('selectcourseprompt', 'local_timespent'),
    'nodataavailable' => get_string('nodataavailable', 'local_timespent'),
    'showingrecordsformat' => $showingrecordsformat,
    'colcount' => count($tableheader),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_timespent/report_index', $data);
echo $OUTPUT->footer();
