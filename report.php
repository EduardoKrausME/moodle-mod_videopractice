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
 * Teacher report for Video Practice.
 *
 * @package   mod_videopractice
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_videopractice\progress_manager;
use mod_videopractice\submission_manager;

require('../../config.php');

$id = required_param('id', PARAM_INT);
$download = optional_param('download', '', PARAM_ALPHA);
$cm = get_coursemodule_from_id('videopractice', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$activity = $DB->get_record('videopractice', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/videopractice:viewreport', $context);

$PAGE->set_url('/mod/videopractice/report.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('report', 'videopractice'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$identityfields = \core_user\fields::for_identity($context)->get_required_fields();
$baseuserfields = [
    'id', 'firstname', 'lastname', 'picture', 'imagealt',
    'firstnamephonetic', 'lastnamephonetic', 'middlename', 'alternatename',
];
$userfields = array_values(array_unique(array_merge($baseuserfields, $identityfields)));
$users = get_enrolled_users(
    $context,
    'mod/videopractice:submit',
    0,
    implode(',', array_map(static fn(string $field): string => 'u.' . $field, $userfields)),
    'u.lastname ASC, u.firstname ASC'
);
$identityheaders = [];
foreach ($identityfields as $field) {
    $identityheaders[] = ['name' => \core_user\fields::get_display_name($field)];
}
$progressmanager = new progress_manager();
$submissionmanager = new submission_manager();
$rows = [];
$csvrows = [];
foreach ($users as $user) {
    $progress = $progressmanager->get((int)$activity->id, (int)$user->id);
    $submission = $submissionmanager->get_latest((int)$activity->id, (int)$user->id);
    $submitted = $submission && in_array($submission->status, [
            submission_manager::STATUS_SUBMITTED,
            submission_manager::STATUS_GRADED,
        ], true);
    $assessment = get_string('assessmentnotavailable', 'videopractice');
    if ($submission && $submission->status === submission_manager::STATUS_SUBMITTED) {
        $assessment = get_string('awaitingassessment', 'videopractice');
    } else if ($submission && $submission->status === submission_manager::STATUS_GRADED) {
        $assessment = get_string('assessed', 'videopractice');
    }
    $submissionstatus = $submission
        ? get_string('status' . $submission->status, 'videopractice')
        : get_string('statusnotstarted', 'videopractice');
    $grade = $submission && $submission->status === submission_manager::STATUS_GRADED && $submission->grade !== null
        ? format_float((float)$submission->grade, 2)
        : '-';
    $lastupdate = 0;
    if ($progress->timemodified) {
        $lastupdate = max($lastupdate, (int)$progress->timemodified);
    }
    if ($submission) {
        $lastupdate = max($lastupdate, (int)$submission->timemodified);
    }
    $canassess = $submission && in_array($submission->status, [
            submission_manager::STATUS_SUBMITTED,
            submission_manager::STATUS_GRADED,
        ], true);
    $identityvalues = [];
    foreach ($identityfields as $field) {
        $identityvalues[] = ['value' => s((string)($user->{$field} ?? ''))];
    }
    $rows[] = [
        'fullname' => fullname($user),
        'profileurl' => (string)new moodle_url('/user/view.php', ['id' => $user->id, 'course' => $course->id]),
        'identityvalues' => $identityvalues,
        'referencepercent' => format_float((float)$progress->percent, 1),
        'referencecomplete' => (bool)$progress->completed,
        'submissionstatus' => $submissionstatus,
        'submitted' => $submitted,
        'assessmentstatus' => $assessment,
        'grade' => $grade,
        'lastupdate' => $lastupdate ? userdate($lastupdate) : '-',
        'canassess' => $canassess,
        'gradeurl' => $canassess ? (string)new moodle_url('/mod/videopractice/grade.php', [
            'id' => $cm->id,
            'submissionid' => $submission->id,
        ]) : '',
    ];
    $csvrow = [fullname($user)];
    foreach ($identityfields as $field) {
        $csvrow[] = (string)($user->{$field} ?? '');
    }
    $csvrow = array_merge($csvrow, [
        format_float((float)$progress->percent, 1),
        $progress->completed ? get_string('yes') : get_string('no'),
        $submissionstatus,
        $assessment,
        $grade,
        $lastupdate ? userdate($lastupdate) : '',
    ]);
    $csvrows[] = $csvrow;
}

if ($download === 'csv') {
    require_capability('mod/videopractice:exportreport', $context);
    require_once($CFG->libdir . '/csvlib.class.php');
    $csv = new csv_export_writer();
    $csv->set_filename(clean_filename($activity->name . '-video-practice-report'));
    $csvheader = [get_string('student', 'videopractice')];
    foreach ($identityfields as $field) {
        $csvheader[] = \core_user\fields::get_display_name($field);
    }
    $csvheader = array_merge($csvheader, [
        get_string('referencewatched', 'videopractice'),
        get_string('referencethreshold', 'videopractice'),
        get_string('submissionstatus', 'videopractice'),
        get_string('assessmentstatus', 'videopractice'),
        get_string('grade', 'grades'),
        get_string('lastupdate', 'videopractice'),
    ]);
    $csv->add_data($csvheader);
    foreach ($csvrows as $csvrow) {
        $csv->add_data($csvrow);
    }
    $csv->download_file();
    exit;
}

$data = [
    'name' => format_string($activity->name),
    'rows' => $rows,
    'hasrows' => (bool)$rows,
    'identityheaders' => $identityheaders,
    'canexport' => has_capability('mod/videopractice:exportreport', $context),
    'exporturl' => (string)new moodle_url('/mod/videopractice/report.php', ['id' => $cm->id, 'download' => 'csv']),
    'backurl' => (string)new moodle_url('/mod/videopractice/view.php', ['id' => $cm->id]),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_videopractice/report', $data);
echo $OUTPUT->footer();
