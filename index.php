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
 * Lists Video Practice activities in a course.
 *
 * @package   mod_videopractice
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

$id = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
require_course_login($course);

$PAGE->set_url('/mod/videopractice/index.php', ['id' => $course->id]);
$PAGE->set_title(get_string('modulenameplural', 'videopractice'));
$PAGE->set_heading(format_string($course->fullname));

$instances = get_all_instances_in_course('videopractice', $course);
$items = [];
foreach ($instances as $instance) {
    if (empty($instance->visible) && !has_capability('moodle/course:viewhiddenactivities',
            context_course::instance($course->id))) {
        continue;
    }
    $items[] = [
        'name' => format_string($instance->name),
        'url' => (string)new moodle_url('/mod/videopractice/view.php', ['id' => $instance->coursemodule]),
        'section' => get_section_name($course, $instance->section),
    ];
}

$data = [
    'items' => $items,
    'hasitems' => (bool)$items,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_videopractice/index', $data);
echo $OUTPUT->footer();
