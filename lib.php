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
 * Core callbacks for Video Practice.
 *
 * @package   mod_videopractice
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_videopractice\player;
use mod_videopractice\stage_manager;
use mod_videopractice\submission_manager;

/**
 * Declares supported Moodle features.
 *
 * @param string $feature Feature constant.
 * @return bool|int|null
 */
function videopractice_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_OTHER;
        case FEATURE_GROUPS:
            return false;
        case FEATURE_GROUPINGS:
            return false;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_ASSESSMENT;
        default:
            return null;
    }
}

/**
 * Adds a Video Practice activity.
 *
 * @param stdClass $data Activity form data.
 * @param mod_videopractice_mod_form|null $mform Form instance.
 * @return int New instance id.
 */
function videopractice_add_instance(stdClass $data, ?mod_videopractice_mod_form $mform = null): int {
    global $DB;
    $now = time();
    $data->timecreated = $now;
    $data->timemodified = $now;
    $data->referenceurl = trim((string)($data->referenceurl ?? ''));
    $id = $DB->insert_record('videopractice', $data);
    $data->id = $id;
    $context = context_module::instance((int)$data->coursemodule);
    player::save_reference_file($data, $context);
    (new stage_manager())->create_default($id);
    videopractice_grade_item_update($data);
    return $id;
}

/**
 * Updates a Video Practice activity.
 *
 * @param stdClass $data Activity form data.
 * @param mod_videopractice_mod_form|null $mform Form instance.
 * @return bool
 */
function videopractice_update_instance(stdClass $data, ?mod_videopractice_mod_form $mform = null): bool {
    global $DB;
    $data->id = $data->instance;
    $data->timemodified = time();
    $data->referenceurl = trim((string)($data->referenceurl ?? ''));
    $result = $DB->update_record('videopractice', $data);
    $context = context_module::instance((int)$data->coursemodule);
    player::save_reference_file($data, $context);
    videopractice_grade_item_update($data);
    videopractice_update_grades($data);
    return $result;
}

/**
 * Deletes an activity and all owned data.
 *
 * @param int $id Activity id.
 * @return bool
 */
function videopractice_delete_instance(int $id): bool {
    global $DB;
    $activity = $DB->get_record('videopractice', ['id' => $id]);
    if (!$activity) {
        return false;
    }
    $cm = get_coursemodule_from_instance('videopractice', $id, $activity->course, false, IGNORE_MISSING);
    if ($cm) {
        $context = context_module::instance($cm->id);
        get_file_storage()->delete_area_files($context->id, 'mod_videopractice');
    }
    $submissionids = $DB->get_fieldset_select('videopractice_submissions', 'id',
        'videopracticeid = :activityid', ['activityid' => $id]);
    if ($submissionids) {
        [$insql, $params] = $DB->get_in_or_equal($submissionids, SQL_PARAMS_NAMED, 'submission');
        $DB->delete_records_select('videopractice_stagegrades', "submissionid {$insql}", $params);
    }
    $DB->delete_records('videopractice_submissions', ['videopracticeid' => $id]);
    $DB->delete_records('videopractice_progress', ['videopracticeid' => $id]);
    $DB->delete_records('videopractice_stages', ['videopracticeid' => $id]);
    $DB->delete_records('videopractice', ['id' => $id]);
    videopractice_grade_item_delete($activity);
    return true;
}

/**
 * Serves protected reference and student submission videos.
 *
 * @param stdClass $course Course.
 * @param stdClass $cm Course module.
 * @param context $context Context.
 * @param string $filearea File area.
 * @param array $args File path arguments.
 * @param bool $forcedownload Force download.
 * @param array $options Serving options.
 * @return bool
 */
function mod_videopractice_pluginfile($course, $cm, $context, string $filearea, array $args,
                                      bool $forcedownload, array $options = []): bool {
    global $DB, $USER;
    if ($context->contextlevel !== CONTEXT_MODULE || !in_array($filearea, ['referencevideo', 'submission'], true)) {
        return false;
    }
    require_login($course, true, $cm);
    require_capability('mod/videopractice:view', $context);
    $itemid = (int)array_shift($args);
    if ($filearea === 'referencevideo' && $itemid !== 0) {
        return false;
    }
    if ($filearea === 'submission') {
        $submission = $DB->get_record('videopractice_submissions', ['id' => $itemid], '*', MUST_EXIST);
        if ((int)$submission->videopracticeid !== (int)$cm->instance) {
            return false;
        }
        $isowner = (int)$submission->userid === (int)$USER->id;
        if (!$isowner && !has_capability('mod/videopractice:grade', $context)) {
            return false;
        }
    }
    $filename = array_pop($args);
    $filepath = '/' . ($args ? implode('/', $args) . '/' : '');
    $file = get_file_storage()->get_file(
        $context->id,
        'mod_videopractice',
        $filearea,
        $itemid,
        $filepath,
        $filename
    );
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, $forcedownload, $options);
    return true;
}

/**
 * File areas exposed by the module.
 *
 * @param stdClass $course Course.
 * @param stdClass $cm Course module.
 * @param context $context Context.
 * @return array
 */
function videopractice_get_file_areas($course, $cm, $context): array {
    return [
        'referencevideo' => get_string('referencevideo', 'videopractice'),
        'submission' => get_string('practicevideo', 'videopractice'),
    ];
}

/**
 * Creates or updates the gradebook item.
 *
 * @param stdClass $activity Activity.
 * @param array|null $grades Grade records.
 * @return int
 */
function videopractice_grade_item_update(stdClass $activity, ?array $grades = null): int {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    $maxgrade = max(0.0, (float)$activity->grade);
    $item = [
        'itemname' => clean_param($activity->name, PARAM_NOTAGS),
        'gradetype' => $maxgrade > 0 ? GRADE_TYPE_VALUE : GRADE_TYPE_NONE,
        'grademin' => 0,
        'grademax' => $maxgrade > 0 ? $maxgrade : 100,
    ];
    return grade_update('mod/videopractice', $activity->course, 'mod', 'videopractice',
        $activity->id, 0, $grades, $item);
}

/**
 * Pushes latest graded attempts to the Moodle gradebook.
 *
 * @param stdClass $activity Activity.
 * @param int $userid Optional user id.
 * @param bool $nullifnone Whether to clear a missing grade.
 * @return void
 */
function videopractice_update_grades(stdClass $activity, int $userid = 0, bool $nullifnone = true): void {
    global $DB;
    $params = ['activityid' => $activity->id, 'status' => submission_manager::STATUS_GRADED];
    $select = 'videopracticeid = :activityid AND status = :status';
    if ($userid) {
        $select .= ' AND userid = :userid';
        $params['userid'] = $userid;
    }
    $records = $DB->get_records_select('videopractice_submissions', $select, $params,
        'userid ASC, attemptnumber DESC');
    $grades = [];
    foreach ($records as $record) {
        if (isset($grades[$record->userid])) {
            continue;
        }
        $grades[$record->userid] = (object)[
            'userid' => $record->userid,
            'rawgrade' => $record->grade,
        ];
    }
    if (!$grades && $userid && $nullifnone) {
        $grades[$userid] = (object)['userid' => $userid, 'rawgrade' => null];
    }
    videopractice_grade_item_update($activity, $grades);
}

/**
 * Deletes the gradebook item.
 *
 * @param stdClass $activity Activity.
 * @return int
 */
function videopractice_grade_item_delete(stdClass $activity): int {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    return grade_update('mod/videopractice', $activity->course, 'mod', 'videopractice',
        $activity->id, 0, null, ['deleted' => 1]);
}

/**
 * Returns course page cache information.
 *
 * @param stdClass $cm Course module record.
 * @return cached_cm_info|null
 */
function videopractice_get_coursemodule_info(stdClass $cm) {
    global $DB;
    $activity = $DB->get_record(
        'videopractice',
        ['id' => $cm->instance],
        'id,name,intro,introformat,completionpercent,completionrequirepractice'
    );
    if (!$activity) {
        return false;
    }
    $info = new cached_cm_info();
    $info->name = $activity->name;
    if ($cm->showdescription) {
        $info->content = format_module_intro('videopractice', $activity, $cm->id, false);
    }
    if ((int)$cm->completion === COMPLETION_TRACKING_AUTOMATIC) {
        $info->customdata['customcompletionrules']['completionpercent'] = (int)$activity->completionpercent;
        $info->customdata['customcompletionrules']['completionrequirepractice'] =
            (int)$activity->completionrequirepractice;
    }
    return $info;
}

/**
 * Returns descriptions of active custom completion rules for course pages.
 *
 * @param cached_cm_info $cm Cached module information.
 * @return array
 */
function mod_videopractice_get_completion_active_rule_descriptions(cached_cm_info $cm): array {
    if ((int)$cm->completion !== COMPLETION_TRACKING_AUTOMATIC ||
        empty($cm->customdata['customcompletionrules']['completionpercent'])) {
        return [];
    }
    $rules = $cm->customdata['customcompletionrules'];
    $descriptions = [
        get_string('completionpercentdesc', 'videopractice', (int)$rules['completionpercent']),
    ];
    if (!empty($rules['completionrequirepractice'])) {
        $descriptions[] = get_string('completionrequirepracticedesc', 'videopractice');
    }
    return $descriptions;
}

/**
 * Adds module-specific settings navigation entries.
 *
 * @param settings_navigation $settingsnav Settings navigation.
 * @param navigation_node $modulenode Module node.
 * @return void
 */
function videopractice_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $modulenode): void {
    global $PAGE;
    if (!$PAGE->cm) {
        return;
    }
    $context = context_module::instance($PAGE->cm->id);
    if (has_capability('mod/videopractice:managestages', $context)) {
        $modulenode->add(
            get_string('managestages', 'videopractice'),
            new moodle_url('/mod/videopractice/stages.php', ['id' => $PAGE->cm->id]),
            navigation_node::TYPE_SETTING
        );
    }
    if (has_capability('mod/videopractice:viewreport', $context)) {
        $modulenode->add(
            get_string('report', 'videopractice'),
            new moodle_url('/mod/videopractice/report.php', ['id' => $PAGE->cm->id]),
            navigation_node::TYPE_SETTING
        );
    }
}
