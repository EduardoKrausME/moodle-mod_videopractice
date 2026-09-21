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
 * Multipart upload endpoint for browser-recorded practice videos.
 *
 * @package   mod_videopractice
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_DEBUG_DISPLAY', true);
require('../../config.php');

$id = required_param('id', PARAM_INT);
$submissionid = required_param('submissionid', PARAM_INT);
$cm = get_coursemodule_from_id('videopractice', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$activity = $DB->get_record('videopractice', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/videopractice:submit', $context);
require_sesskey();

$response = ['success' => false, 'message' => get_string('recordinguploadfailed', 'videopractice')];
try {
    $submission = $DB->get_record('videopractice_submissions', [
        'id' => $submissionid,
        'videopracticeid' => $activity->id,
        'userid' => $USER->id,
    ], '*', MUST_EXIST);
    if ($submission->status !== \mod_videopractice\submission_manager::STATUS_DRAFT) {
        throw new moodle_exception('submissionnoteditable', 'videopractice');
    }
    if (empty($activity->allowrecording)) {
        throw new moodle_exception('recordingdisabled', 'videopractice');
    }
    if (!isset($_FILES['recording']) || $_FILES['recording']['error'] !== UPLOAD_ERR_OK) {
        throw new moodle_exception('recordinguploadfailed', 'videopractice');
    }
    $upload = $_FILES['recording'];
    $maxbytes = get_max_upload_file_size($CFG->maxbytes, $course->maxbytes, (int)$activity->submissionmaxbytes);
    if ($maxbytes > 0 && (int)$upload['size'] > $maxbytes) {
        throw new moodle_exception('recordingtoolarge', 'videopractice');
    }
    $mimetype = strtolower((string)$upload['type']);
    $allowed = [
        'video/webm' => 'webm',
        'video/mp4' => 'mp4',
        'video/quicktime' => 'mov',
        'video/ogg' => 'ogv',
        'application/octet-stream' => 'webm',
    ];
    if (!isset($allowed[$mimetype])) {
        throw new moodle_exception('recordinginvalidtype', 'videopractice');
    }
    if (!is_uploaded_file($upload['tmp_name'])) {
        throw new moodle_exception('recordinguploadfailed', 'videopractice');
    }
    $filename = 'practice-' . $submission->attemptnumber . '.' . $allowed[$mimetype];
    $fs = get_file_storage();
    $fs->delete_area_files($context->id, 'mod_videopractice', 'submission', $submission->id);
    $filerecord = [
        'contextid' => $context->id,
        'component' => 'mod_videopractice',
        'filearea' => 'submission',
        'itemid' => $submission->id,
        'filepath' => '/',
        'filename' => $filename,
        'userid' => $USER->id,
    ];
    $fs->create_file_from_pathname($filerecord, $upload['tmp_name']);
    $submission->timemodified = time();
    $DB->update_record('videopractice_submissions', $submission);
    $response = ['success' => true, 'message' => get_string('recordingsaved', 'videopractice')];
} catch (Throwable $exception) {
    $response['message'] = $exception instanceof moodle_exception
        ? $exception->getMessage()
        : get_string('recordinguploadfailed', 'videopractice');
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($response);
