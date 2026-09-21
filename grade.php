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
 * Teacher grading page for a practice submission.
 *
 * @package   mod_videopractice
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_videopractice\form\grading_form;
use mod_videopractice\stage_manager;
use mod_videopractice\submission_manager;

require('../../config.php');

$id = required_param('id', PARAM_INT);
$submissionid = required_param('submissionid', PARAM_INT);
$returnediting = optional_param('returnediting', 0, PARAM_BOOL);
$cm = get_coursemodule_from_id('videopractice', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$activity = $DB->get_record('videopractice', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/videopractice:grade', $context);

$submission = $DB->get_record('videopractice_submissions', [
    'id' => $submissionid,
    'videopracticeid' => $activity->id,
], '*', MUST_EXIST);
if (!in_array($submission->status, [submission_manager::STATUS_SUBMITTED, submission_manager::STATUS_GRADED], true)) {
    throw new moodle_exception('submissionnotready', 'videopractice');
}
$student = $DB->get_record('user', ['id' => $submission->userid], '*', MUST_EXIST);
$manager = new submission_manager();
if ($returnediting) {
    require_sesskey();
    $manager->reopen($activity, $submission);
    redirect(new moodle_url('/mod/videopractice/report.php', ['id' => $cm->id]),
        get_string('submissionreopened', 'videopractice'));
}

$PAGE->set_url('/mod/videopractice/grade.php', ['id' => $cm->id, 'submissionid' => $submissionid]);
$PAGE->set_title(get_string('assesspractice', 'videopractice'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$stages = (new stage_manager())->get_all((int)$activity->id);
$form = new grading_form(null, ['stages' => $stages]);
$defaults = (object)[
    'id' => $cm->id,
    'submissionid' => $submission->id,
    'feedback' => $submission->feedback ?? '',
];
$currentgrades = $DB->get_records('videopractice_stagegrades', ['submissionid' => $submission->id]);
foreach ($stages as $stage) {
    $current = null;
    foreach ($currentgrades as $grade) {
        if ((int)$grade->stageid === (int)$stage->id) {
            $current = $grade;
            break;
        }
    }
    $defaults->{'score_' . $stage->id} = $current ? $current->score : '';
    $defaults->{'feedback_' . $stage->id} = $current ? $current->feedback : '';
}
$form->set_data($defaults);
if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/videopractice/report.php', ['id' => $cm->id]));
}
if ($data = $form->get_data()) {
    $manager->grade($activity, $submission, $stages, $data, (int)$USER->id);
    redirect(new moodle_url('/mod/videopractice/report.php', ['id' => $cm->id]),
        get_string('assessmentsaved', 'videopractice'));
}

$videourl = $manager->file_url($submission, $context);
$headerdata = [
    'student' => fullname($student),
    'attemptnumber' => (int)$submission->attemptnumber,
    'video' => $videourl,
    'hasvideo' => $videourl !== '',
    'returnurl' => (string)new moodle_url('/mod/videopractice/grade.php', [
        'id' => $cm->id,
        'submissionid' => $submission->id,
        'returnediting' => 1,
        'sesskey' => sesskey(),
    ]),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_videopractice/grading_header', $headerdata);
$form->display();
echo $OUTPUT->footer();
