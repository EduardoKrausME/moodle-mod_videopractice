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
 * Main Video Practice activity page.
 *
 * @package   mod_videopractice
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_videopractice\player;
use mod_videopractice\progress_manager;
use mod_videopractice\stage_manager;
use mod_videopractice\submission_manager;

require('../../config.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('videopractice', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$activity = $DB->get_record('videopractice', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/videopractice:view', $context);

$PAGE->set_url('/mod/videopractice/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($activity->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$completion = new completion_info($course);
if ($completion->is_enabled($cm)) {
    $completion->set_module_viewed($cm);
}
$event = mod_videopractice\event\course_module_viewed::create([
    'objectid' => $activity->id,
    'context' => $context,
]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('videopractice', $activity);
$event->trigger();

$progress = (new progress_manager())->get((int)$activity->id, (int)$USER->id);
$playerconfig = player::build($activity, $context);
$stages = (new stage_manager())->get_all((int)$activity->id);
$stagedata = [];
foreach ($stages as $stage) {
    $stagedata[$stage->id] = [
        'id' => $stage->id,
        'name' => format_string($stage->name),
        'instructions' => format_text((string)$stage->instructions, FORMAT_HTML),
        'hasinstructions' => trim((string)$stage->instructions) !== '',
        'maxscore' => format_float((float)$stage->maxscore, 2),
        'score' => '',
        'feedback' => '',
        'hasgrade' => false,
    ];
}

$submissionmanager = new submission_manager();
$submission = null;
$cansubmit = has_capability('mod/videopractice:submit', $context);
if ($cansubmit) {
    $submission = $submissionmanager->get_latest((int)$activity->id, (int)$USER->id);
    if ($submission && $submission->status === submission_manager::STATUS_GRADED) {
        $stagegrades = $DB->get_records('videopractice_stagegrades', ['submissionid' => $submission->id]);
        foreach ($stagegrades as $stagegrade) {
            if (!isset($stagedata[$stagegrade->stageid])) {
                continue;
            }
            $stagedata[$stagegrade->stageid]['score'] = format_float((float)$stagegrade->score, 2);
            $stagedata[$stagegrade->stageid]['feedback'] = format_text((string)$stagegrade->feedback, FORMAT_HTML);
            $stagedata[$stagegrade->stageid]['hasgrade'] = true;
        }
    }
}

$status = '';
$statusclass = 'secondary';
if ($submission) {
    $status = get_string('status' . $submission->status, 'videopractice');
    if ($submission->status === submission_manager::STATUS_SUBMITTED) {
        $statusclass = 'info';
    } else if ($submission->status === submission_manager::STATUS_GRADED) {
        $statusclass = 'success';
    }
}

$segments = json_decode((string)$progress->watchedsegments, true);
if (!is_array($segments)) {
    $segments = [];
}
$trackerconfig = [
    'cmid' => (int)$cm->id,
    'source' => (string)$activity->referencesource,
    'lastposition' => (float)$progress->lastposition,
    'resumeplayback' => (bool)$activity->resumeplayback,
    'allowseek' => (bool)$activity->allowseek,
    'segments' => $segments,
    'youtubeid' => $playerconfig['youtubeid'],
    'vimeoid' => $playerconfig['vimeoid'],
];
$PAGE->requires->js_call_amd('mod_videopractice/tracker', 'init', [$trackerconfig]);

$templatedata = [
    'name' => format_string($activity->name),
    'intro' => format_module_intro('videopractice', $activity, $cm->id),
    'hasintro' => trim((string)$activity->intro) !== '',
    'player' => $playerconfig,
    'percent' => format_float((float)$progress->percent, 1),
    'percentrounded' => (int)round((float)$progress->percent),
    'requiredpercent' => (int)$activity->completionpercent,
    'referencecomplete' => (bool)$progress->completed,
    'stages' => array_values($stagedata),
    'hasstages' => (bool)$stagedata,
    'cansubmit' => $cansubmit,
    'submissionurl' => (string)new moodle_url('/mod/videopractice/submission.php', ['id' => $cm->id]),
    'hassubmission' => (bool)$submission,
    'submissionstatus' => $status,
    'submissionstatusclass' => $statusclass,
    'attemptnumber' => $submission ? (int)$submission->attemptnumber : 0,
    'isgraded' => $submission && $submission->status === submission_manager::STATUS_GRADED,
    'submissiongrade' => $submission && $submission->grade !== null ? format_float((float)$submission->grade, 2) : '',
    'maxgrade' => format_float((float)$activity->grade, 2),
    'overallfeedback' => $submission && $submission->status === submission_manager::STATUS_GRADED
        ? format_text((string)$submission->feedback, (int)$submission->feedbackformat)
        : '',
    'hasoverallfeedback' => $submission && $submission->status === submission_manager::STATUS_GRADED &&
        trim((string)$submission->feedback) !== '',
    'canmanagestages' => has_capability('mod/videopractice:managestages', $context),
    'stagesurl' => (string)new moodle_url('/mod/videopractice/stages.php', ['id' => $cm->id]),
    'canviewreport' => has_capability('mod/videopractice:viewreport', $context),
    'reporturl' => (string)new moodle_url('/mod/videopractice/report.php', ['id' => $cm->id]),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_videopractice/view', $templatedata);
echo $OUTPUT->footer();
