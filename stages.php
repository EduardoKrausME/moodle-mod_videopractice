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
 * Stage management page.
 *
 * @package   mod_videopractice
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_videopractice\stage_manager;

require('../../config.php');

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$stageid = optional_param('stageid', 0, PARAM_INT);
$cm = get_coursemodule_from_id('videopractice', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$activity = $DB->get_record('videopractice', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/videopractice:managestages', $context);

$PAGE->set_url('/mod/videopractice/stages.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('managestages', 'videopractice'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$manager = new stage_manager();
if ($action !== '' && $stageid) {
    require_sesskey();
    $stage = $DB->get_record('videopractice_stages', [
        'id' => $stageid,
        'videopracticeid' => $activity->id,
    ], '*', MUST_EXIST);
    if ($action === 'up') {
        $manager->move((int)$activity->id, $stageid, -1);
    } else if ($action === 'down') {
        $manager->move((int)$activity->id, $stageid, 1);
    } else if ($action === 'delete') {
        $stages = $manager->get_all((int)$activity->id);
        if (count($stages) <= 1) {
            redirect($PAGE->url, get_string('cannotdeletelaststage', 'videopractice'), null,
                \core\output\notification::NOTIFY_ERROR);
        }
        $DB->delete_records('videopractice_stagegrades', ['stageid' => $stage->id]);
        $DB->delete_records('videopractice_stages', ['id' => $stage->id]);
    }
    redirect($PAGE->url);
}

$stages = $manager->get_all((int)$activity->id);
$items = [];
foreach ($stages as $index => $stage) {
    $items[] = [
        'name' => format_string($stage->name),
        'instructions' => format_text((string)$stage->instructions, FORMAT_HTML),
        'hasinstructions' => trim((string)$stage->instructions) !== '',
        'maxscore' => format_float((float)$stage->maxscore, 2),
        'editurl' => (string)new moodle_url('/mod/videopractice/stage_edit.php', [
            'id' => $cm->id,
            'stageid' => $stage->id,
        ]),
        'upurl' => (string)new moodle_url('/mod/videopractice/stages.php', [
            'id' => $cm->id, 'stageid' => $stage->id, 'action' => 'up', 'sesskey' => sesskey(),
        ]),
        'downurl' => (string)new moodle_url('/mod/videopractice/stages.php', [
            'id' => $cm->id, 'stageid' => $stage->id, 'action' => 'down', 'sesskey' => sesskey(),
        ]),
        'deleteurl' => (string)new moodle_url('/mod/videopractice/stages.php', [
            'id' => $cm->id, 'stageid' => $stage->id, 'action' => 'delete', 'sesskey' => sesskey(),
        ]),
        'canup' => $index > 0,
        'candown' => $index < count($stages) - 1,
        'candelete' => count($stages) > 1,
    ];
}

$data = [
    'name' => format_string($activity->name),
    'stages' => $items,
    'addurl' => (string)new moodle_url('/mod/videopractice/stage_edit.php', ['id' => $cm->id]),
    'backurl' => (string)new moodle_url('/mod/videopractice/view.php', ['id' => $cm->id]),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_videopractice/stages', $data);
echo $OUTPUT->footer();
