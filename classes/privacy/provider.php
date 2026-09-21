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
 * Privacy provider for Video Practice.
 *
 * @package   mod_videopractice
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videopractice\privacy;

use context;
use context_module;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Describes and manages personal data stored by Video Practice.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Describes personal data stored by the plugin.
     *
     * @param collection $collection Metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('videopractice_progress', [
            'userid' => 'privacy:metadata:progress:userid',
            'watchedsegments' => 'privacy:metadata:progress:watchedsegments',
            'percent' => 'privacy:metadata:progress:percent',
        ], 'privacy:metadata:progress');
        $collection->add_database_table('videopractice_submissions', [
            'userid' => 'privacy:metadata:submissions:userid',
            'status' => 'privacy:metadata:submissions:status',
            'grade' => 'privacy:metadata:submissions:grade',
            'feedback' => 'privacy:metadata:submissions:feedback',
            'graderid' => 'privacy:metadata:submissions:graderid',
        ], 'privacy:metadata:submissions');
        $collection->add_database_table('videopractice_stagegrades', [
            'score' => 'privacy:metadata:stagegrades:score',
            'feedback' => 'privacy:metadata:stagegrades:feedback',
        ], 'privacy:metadata:stagegrades');
        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:practicevideo');
        return $collection;
    }

    /**
     * Gets module contexts containing data for a user.
     *
     * @param int $userid User id.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT DISTINCT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid
                  JOIN {modules} m ON m.id = cm.module
                  JOIN {videopractice} vp ON vp.id = cm.instance
             LEFT JOIN {videopractice_progress} p
                    ON p.videopracticeid = vp.id AND p.userid = :progressuserid
             LEFT JOIN {videopractice_submissions} s
                    ON s.videopracticeid = vp.id AND (s.userid = :submissionuserid OR s.graderid = :graderuserid)
                 WHERE ctx.contextlevel = :contextlevel
                   AND m.name = :modname
                   AND (p.id IS NOT NULL OR s.id IS NOT NULL)";
        $contextlist->add_from_sql($sql, [
            'progressuserid' => $userid,
            'submissionuserid' => $userid,
            'graderuserid' => $userid,
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'videopractice',
        ]);
        return $contextlist;
    }

    /**
     * Exports a user's Video Practice data.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $user = $contextlist->get_user();
        foreach ($contextlist as $context) {
            if (!$context instanceof context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('videopractice', $context->instanceid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                continue;
            }
            $progress = $DB->get_record('videopractice_progress', [
                'videopracticeid' => $cm->instance,
                'userid' => $user->id,
            ]);
            if ($progress) {
                $data = (object)[
                    'duration' => $progress->duration,
                    'lastposition' => $progress->lastposition,
                    'watchedsegments' => $progress->watchedsegments,
                    'percent' => $progress->percent,
                    'completed' => transform::yesno($progress->completed),
                    'timemodified' => transform::datetime($progress->timemodified),
                ];
                writer::with_context($context)->export_data([get_string('referenceprogress', 'videopractice')], $data);
            }

            $submissions = $DB->get_records('videopractice_submissions', [
                'videopracticeid' => $cm->instance,
                'userid' => $user->id,
            ], 'attemptnumber ASC');
            foreach ($submissions as $submission) {
                $stages = $DB->get_records('videopractice_stagegrades', ['submissionid' => $submission->id]);
                $stageexport = [];
                foreach ($stages as $stage) {
                    $stageexport[] = (object)[
                        'stageid' => $stage->stageid,
                        'score' => $stage->score,
                        'feedback' => $stage->feedback,
                    ];
                }
                $data = (object)[
                    'attemptnumber' => $submission->attemptnumber,
                    'status' => $submission->status,
                    'timesubmitted' => $submission->timesubmitted ? transform::datetime($submission->timesubmitted) : '',
                    'grade' => $submission->grade,
                    'feedback' => $submission->feedback,
                    'timegraded' => $submission->timegraded ? transform::datetime($submission->timegraded) : '',
                    'stages' => $stageexport,
                ];
                $path = [get_string('yourpractice', 'videopractice'),
                    get_string('attempt', 'videopractice') . ' ' . $submission->attemptnumber];
                $writer = writer::with_context($context);
                $writer->export_data($path, $data);
                $writer->export_area_files($path, 'mod_videopractice', 'submission', $submission->id);
            }
        }
    }

    /**
     * Deletes all user data for one activity context.
     *
     * @param context $context Context.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if (!$context instanceof context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('videopractice', $context->instanceid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }
        $submissions = $DB->get_records('videopractice_submissions', ['videopracticeid' => $cm->instance], '', 'id');
        self::delete_submission_records(array_keys($submissions), $context);
        $DB->delete_records('videopractice_progress', ['videopracticeid' => $cm->instance]);
    }

    /**
     * Deletes data for one user in approved contexts.
     *
     * @param approved_contextlist $contextlist Approved context list.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist as $context) {
            if (!$context instanceof context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('videopractice', $context->instanceid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                continue;
            }
            $submissions = $DB->get_records('videopractice_submissions', [
                'videopracticeid' => $cm->instance,
                'userid' => $userid,
            ], '', 'id');
            self::delete_submission_records(array_keys($submissions), $context);
            $DB->delete_records('videopractice_progress', [
                'videopracticeid' => $cm->instance,
                'userid' => $userid,
            ]);
            $DB->set_field('videopractice_submissions', 'graderid', null, [
                'videopracticeid' => $cm->instance,
                'graderid' => $userid,
            ]);
        }
    }

    /**
     * Adds users with personal data in the context to a user list.
     *
     * @param userlist $userlist User list.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('videopractice', $context->instanceid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }
        $params = ['activityid' => $cm->instance];
        $userlist->add_from_sql('userid',
            'SELECT userid FROM {videopractice_progress} WHERE videopracticeid = :activityid', $params);
        $userlist->add_from_sql('userid',
            'SELECT userid FROM {videopractice_submissions} WHERE videopracticeid = :activityid', $params);
        $userlist->add_from_sql('graderid',
            'SELECT graderid FROM {videopractice_submissions}
            WHERE videopracticeid = :activityid AND graderid IS NOT NULL', $params);
    }

    /**
     * Deletes personal data for an approved user list in a context.
     *
     * @param approved_userlist $userlist Approved users.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('videopractice', $context->instanceid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }
        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'privacyuser');
        $params = ['activityid' => $cm->instance] + $inparams;
        $submissionids = $DB->get_fieldset_select('videopractice_submissions', 'id',
            "videopracticeid = :activityid AND userid {$insql}", $params);
        self::delete_submission_records($submissionids, $context);
        $DB->delete_records_select('videopractice_progress',
            "videopracticeid = :activityid AND userid {$insql}", $params);
        $DB->set_field_select('videopractice_submissions', 'graderid', null,
            "videopracticeid = :activityid AND graderid {$insql}", $params);
    }

    /**
     * Deletes submission records and their files.
     *
     * @param array $submissionids Submission ids.
     * @param context_module $context Module context.
     * @return void
     */
    private static function delete_submission_records(array $submissionids, context_module $context): void {
        global $DB;

        if (!$submissionids) {
            return;
        }
        $fs = get_file_storage();
        foreach ($submissionids as $submissionid) {
            $fs->delete_area_files($context->id, 'mod_videopractice', 'submission', (int)$submissionid);
        }
        [$insql, $params] = $DB->get_in_or_equal($submissionids, SQL_PARAMS_NAMED, 'submission');
        $DB->delete_records_select('videopractice_stagegrades', "submissionid {$insql}", $params);
        $DB->delete_records_select('videopractice_submissions', "id {$insql}", $params);
    }
}
