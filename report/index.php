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
require_once($CFG->dirroot . '/local/timespent/locallib.php');

$context = context_system::instance();
require_login();
local_timespent_require_view_report($context);

// Optional shared helpers (course typing only) — report UI stays self-contained.
if (file_exists($CFG->dirroot . '/local/slmscommon/locallib.php')) {
    require_once($CFG->dirroot . '/local/slmscommon/locallib.php');
}

$heading = get_string('timespent_report', 'local_timespent');
$url = new moodle_url('/local/timespent/report/index.php');
$reporturl = new moodle_url('/report/index.php');
$reportheading = get_string('heading', 'core_report');

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->add_body_class('limitedwidth');
$PAGE->set_heading($heading);
$PAGE->set_title($heading);
$PAGE->navbar->add($reportheading, $reporturl);
$PAGE->navbar->add($heading, $url);

// Prefer shared site report styling when present; plugin CSS always loads as a fallback.
$commoncss = $CFG->dirroot . '/commonresources/css/style.css';
$datatablescss = $CFG->dirroot . '/commonresources/css/datatables.min.css';
if (file_exists($commoncss)) {
    $PAGE->requires->css('/commonresources/css/style.css?v=' . filemtime($commoncss));
}
if (file_exists($datatablescss)) {
    $PAGE->requires->css('/commonresources/css/datatables.min.css');
}
$PAGE->requires->css('/local/timespent/styles.css');

$PAGE->requires->jquery();
$reportjs = '/local/timespent/js/report/index.js';
$PAGE->requires->js(new moodle_url($reportjs, ['v' => filemtime($CFG->dirroot . $reportjs)]), true);

$courses = [
    ['id' => 0, 'fullname' => get_string('select_course', 'local_timespent')],
];
foreach (get_courses() as $course) {
    // Exclude the front page / whole-site course.
    if ((int) $course->id === (int) SITEID || (int) $course->category === 0) {
        continue;
    }
    // Match other product reports: only standard courses when helper exists.
    if (function_exists('slms_get_course_type') && slms_get_course_type($course->id) !== 'course') {
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

$loadinggifurl = '';
if (file_exists($CFG->dirroot . '/commonresources/images/loading.gif')) {
    $loadinggifurl = (new moodle_url('/commonresources/images/loading.gif'))->out(false);
} else {
    $loadinggifurl = $OUTPUT->image_url('i/loading', 'core')->out(false);
}

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
    'colcount' => count($tableheader),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_timespent/report_index', $data);
echo $OUTPUT->footer();
