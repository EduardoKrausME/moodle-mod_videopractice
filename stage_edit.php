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
 * Add/edit a practice stage.
 *
 * @package   mod_videopractice
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_videopractice\form\stage_form;

require('../../config.php');

$id = required_param('id', PARAM_INT);
$stageid = optional_param('stageid', 0, PARAM_INT);
$cm = get_coursemodule_from_id('videopractice', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$activity = $DB->get_record('videopractice', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/videopractice:managestages', $context);

$PAGE->set_url('/mod/videopractice/stage_edit.php', ['id' => $cm->id, 'stageid' => $stageid]);
$PAGE->set_title($stageid ? get_string('editstage', 'videopractice') : get_string('addstage', 'videopractice'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$stage = null;
if ($stageid) {
    $stage = $DB->get_record('videopractice_stages', [
        'id' => $stageid,
        'videopracticeid' => $activity->id,
    ], '*', MUST_EXIST);
}
$form = new stage_form();
$form->set_data((object)[
    'id' => $cm->id,
    'stageid' => $stageid,
    'name' => $stage ? $stage->name : '',
    'instructions' => $stage ? $stage->instructions : '',
    'maxscore' => $stage ? $stage->maxscore : 100,
]);
if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/videopractice/stages.php', ['id' => $cm->id]));
}
if ($data = $form->get_data()) {
    $now = time();
    if ($stage) {
        $stage->name = $data->name;
        $stage->instructions = $data->instructions;
        $stage->maxscore = $data->maxscore;
        $stage->timemodified = $now;
        $DB->update_record('videopractice_stages', $stage);
    } else {
        $maxsort = (int)$DB->get_field_sql(
            'SELECT COALESCE(MAX(sortorder), 0) FROM {videopractice_stages} WHERE videopracticeid = :activityid',
            ['activityid' => $activity->id]
        );
        $DB->insert_record('videopractice_stages', (object)[
            'videopracticeid' => $activity->id,
            'name' => $data->name,
            'instructions' => $data->instructions,
            'maxscore' => $data->maxscore,
            'sortorder' => $maxsort + 10,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }
    redirect(new moodle_url('/mod/videopractice/stages.php', ['id' => $cm->id]),
        get_string('stagesaved', 'videopractice'));
}

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
