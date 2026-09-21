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
 * Student practice submission page.
 *
 * @package   mod_videopractice
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_videopractice\form\submission_form;
use mod_videopractice\submission_manager;

require('../../config.php');

$id = required_param('id', PARAM_INT);
$newattempt = optional_param('newattempt', 0, PARAM_BOOL);
$cm = get_coursemodule_from_id('videopractice', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$activity = $DB->get_record('videopractice', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/videopractice:submit', $context);

$PAGE->set_url('/mod/videopractice/submission.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('practicevideosubmission', 'videopractice'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$manager = new submission_manager();
$latest = $manager->get_latest((int)$activity->id, (int)$USER->id);
if ($newattempt) {
    require_sesskey();
    $editable = $manager->get_editable($activity, (int)$USER->id, true);
    if ($editable) {
        redirect($PAGE->url, get_string('newattemptcreated', 'videopractice'));
    }
    redirect($PAGE->url, get_string('cannotstartnewattempt', 'videopractice'), null,
        \core\output\notification::NOTIFY_ERROR);
}
if (!$latest) {
    $latest = $manager->get_editable($activity, (int)$USER->id, false);
}

$iseditable = $latest && $latest->status === submission_manager::STATUS_DRAFT;
$form = null;
if ($iseditable) {
    $draftid = file_get_submitted_draft_itemid('practicevideo');
    file_prepare_draft_area($draftid, $context->id, 'mod_videopractice', 'submission', $latest->id, [
        'subdirs' => 0,
        'maxfiles' => 1,
        'maxbytes' => (int)$activity->submissionmaxbytes,
        'accepted_types' => ['video'],
    ]);
    $form = new submission_form(null, ['activity' => $activity]);
    $form->set_data((object)[
        'id' => $cm->id,
        'practicevideo' => $draftid,
    ]);
    if ($form->is_cancelled()) {
        redirect(new moodle_url('/mod/videopractice/view.php', ['id' => $cm->id]));
    }
    if ($data = $form->get_data()) {
        $manager->save_draft_file((int)$data->practicevideo, $latest, $context, (int)$activity->submissionmaxbytes);
        if (isset($data->submitpractice)) {
            if (!$manager->has_file($latest, $context)) {
                redirect($PAGE->url, get_string('practicevideorequired', 'videopractice'), null,
                    \core\output\notification::NOTIFY_ERROR);
            }
            $manager->submit($activity, $cm, $latest);
            $event = mod_videopractice\event\submission_submitted::create([
                'objectid' => $latest->id,
                'context' => $context,
                'relateduserid' => $USER->id,
                'other' => ['attemptnumber' => (int)$latest->attemptnumber],
            ]);
            $event->trigger();
            redirect(new moodle_url('/mod/videopractice/view.php', ['id' => $cm->id]),
                get_string('practicesubmitted', 'videopractice'));
        }
        redirect($PAGE->url, get_string('draftsaved', 'videopractice'));
    }
}

$cannewattempt = $latest && $latest->status === submission_manager::STATUS_GRADED &&
    $manager->can_new_attempt($activity, $latest);
$video = $latest ? $manager->file_url($latest, $context) : '';
$status = $latest ? get_string('status' . $latest->status, 'videopractice') : get_string('statusnotstarted', 'videopractice');
$templatedata = [
    'name' => format_string($activity->name),
    'attemptnumber' => $latest ? (int)$latest->attemptnumber : 0,
    'status' => $status,
    'iseditable' => $iseditable,
    'video' => $video,
    'hasvideo' => $video !== '',
    'allowrecording' => $iseditable && !empty($activity->allowrecording),
    'cannewattempt' => $cannewattempt,
    'newattempturl' => (string)new moodle_url('/mod/videopractice/submission.php', [
        'id' => $cm->id,
        'newattempt' => 1,
        'sesskey' => sesskey(),
    ]),
    'backurl' => (string)new moodle_url('/mod/videopractice/view.php', ['id' => $cm->id]),
];

if ($templatedata['allowrecording']) {
    $PAGE->requires->js_call_amd('mod_videopractice/recorder', 'init', [[
        'cmid' => (int)$cm->id,
        'submissionid' => (int)$latest->id,
        'uploadurl' => (string)new moodle_url('/mod/videopractice/upload_recording.php'),
        'maxbytes' => (int)$activity->submissionmaxbytes,
    ]]);
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_videopractice/submission', $templatedata);
if ($form) {
    $form->display();
}
echo $OUTPUT->footer();
