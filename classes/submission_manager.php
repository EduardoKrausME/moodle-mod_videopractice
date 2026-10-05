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
 * Student submission manager.
 *
 * @package   mod_videopractice
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videopractice;

use completion_info;
use context_module;
use moodle_url;
use stdClass;

/**
 * Manages student attempts, files and grades.
 */
class submission_manager {

    /** @var string Draft status. */
    public const STATUS_DRAFT = 'draft';

    /** @var string Submitted status. */
    public const STATUS_SUBMITTED = 'submitted';

    /** @var string Graded status. */
    public const STATUS_GRADED = 'graded';

    /**
     * Gets latest attempt for a user.
     *
     * @param int $activityid Activity id.
     * @param int $userid User id.
     * @return stdClass|null
     */
    public function get_latest(int $activityid, int $userid): ?stdClass {
        global $DB;
        $records = $DB->get_records('videopractice_submissions', [
            'videopracticeid' => $activityid,
            'userid' => $userid,
        ], 'attemptnumber DESC', '*', 0, 1);
        return $records ? reset($records) : null;
    }

    /**
     * Gets or creates the editable draft attempt.
     *
     * @param stdClass $activity Activity.
     * @param int $userid User id.
     * @param bool $createnew Allow a new attempt after grading.
     * @return stdClass|null
     */
    public function get_editable(stdClass $activity, int $userid, bool $createnew = false): ?stdClass {
        global $DB;
        $latest = $this->get_latest((int)$activity->id, $userid);
        if ($latest && $latest->status === self::STATUS_DRAFT) {
            return $latest;
        }
        if (!$latest) {
            return $this->create_attempt($activity, $userid, 1);
        }
        if ($createnew && $latest->status === self::STATUS_GRADED && $this->can_new_attempt($activity, $latest)) {
            return $this->create_attempt($activity, $userid, (int)$latest->attemptnumber + 1);
        }
        return null;
    }

    /**
     * Whether another attempt is permitted.
     *
     * @param stdClass $activity Activity.
     * @param stdClass $latest Latest submission.
     * @return bool
     */
    public function can_new_attempt(stdClass $activity, stdClass $latest): bool {
        return (int)$activity->maxattempts === 0 || (int)$latest->attemptnumber < (int)$activity->maxattempts;
    }

    /**
     * Creates an attempt.
     *
     * @param stdClass $activity Activity.
     * @param int $userid User id.
     * @param int $attemptnumber Attempt number.
     * @return stdClass
     */
    private function create_attempt(stdClass $activity, int $userid, int $attemptnumber): stdClass {
        global $DB;
        $now = time();
        $record = (object)[
            'videopracticeid' => $activity->id,
            'userid' => $userid,
            'attemptnumber' => $attemptnumber,
            'status' => self::STATUS_DRAFT,
            'timesubmitted' => 0,
            'grade' => null,
            'feedback' => null,
            'feedbackformat' => FORMAT_HTML,
            'graderid' => null,
            'timegraded' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $record->id = $DB->insert_record('videopractice_submissions', $record);
        return $record;
    }

    /**
     * Saves the filemanager draft to a submission.
     *
     * @param int $draftid Draft item id.
     * @param stdClass $submission Submission.
     * @param context_module $context Module context.
     * @param int $maxbytes Max bytes.
     * @return void
     */
    public function save_draft_file(int $draftid, stdClass $submission, context_module $context, int $maxbytes): void {
        file_save_draft_area_files($draftid, $context->id, 'mod_videopractice', 'submission', $submission->id, [
            'subdirs' => 0,
            'maxfiles' => 1,
            'maxbytes' => $maxbytes,
            'accepted_types' => ['video'],
        ]);
        $this->touch($submission);
    }

    /**
     * Returns whether a submission has a video.
     *
     * @param stdClass $submission Submission.
     * @param context_module $context Context.
     * @return bool
     */
    public function has_file(stdClass $submission, context_module $context): bool {
        return (bool)get_file_storage()->get_area_files(
            $context->id,
            'mod_videopractice',
            'submission',
            $submission->id,
            'filename',
            false
        );
    }

    /**
     * Returns protected video URL.
     *
     * @param stdClass $submission Submission.
     * @param context_module $context Context.
     * @return string
     */
    public function file_url(stdClass $submission, context_module $context): string {
        $files = get_file_storage()->get_area_files(
            $context->id,
            'mod_videopractice',
            'submission',
            $submission->id,
            'filename',
            false
        );
        if (!$files) {
            return '';
        }
        $file = reset($files);
        return moodle_url::make_pluginfile_url(
            $context->id,
            'mod_videopractice',
            'submission',
            $submission->id,
            $file->get_filepath(),
            $file->get_filename()
        )->out(false);
    }

    /**
     * Marks a draft as submitted for grading.
     *
     * @param stdClass $activity Activity.
     * @param stdClass $cm Course module.
     * @param stdClass $submission Submission.
     * @return void
     */
    public function submit(stdClass $activity, stdClass $cm, stdClass $submission): void {
        global $DB;
        $submission->status = self::STATUS_SUBMITTED;
        $submission->timesubmitted = time();
        $submission->timemodified = time();
        $DB->update_record('videopractice_submissions', $submission);

        $course = $DB->get_record('course', ['id' => $activity->course], '*', MUST_EXIST);
        $completion = new completion_info($course);
        if ($completion->is_enabled($cm) && (int)$cm->completion === COMPLETION_TRACKING_AUTOMATIC) {
            $completion->update_state($cm, COMPLETION_UNKNOWN, $submission->userid);
        }
    }

    /**
     * Stores teacher assessment and stage scores.
     *
     * @param stdClass $activity Activity.
     * @param stdClass $submission Submission.
     * @param array $stages Ordered stages.
     * @param stdClass $data Form data.
     * @param int $graderid Grader user id.
     * @return float Final grade.
     */
    public function grade(stdClass $activity, stdClass $submission, array $stages, stdClass $data, int $graderid): float {
        global $DB;
        $total = 0.0;
        $max = 0.0;
        foreach ($stages as $stage) {
            $field = 'score_' . $stage->id;
            $feedbackfield = 'feedback_' . $stage->id;
            $score = max(0.0, min((float)$stage->maxscore, (float)($data->{$field} ?? 0)));
            $max += (float)$stage->maxscore;
            $total += $score;
            $existing = $DB->get_record('videopractice_stagegrades', [
                'submissionid' => $submission->id,
                'stageid' => $stage->id,
            ]);
            $record = (object)[
                'submissionid' => $submission->id,
                'stageid' => $stage->id,
                'score' => $score,
                'feedback' => (string)($data->{$feedbackfield} ?? ''),
                'feedbackformat' => FORMAT_HTML,
                'timemodified' => time(),
            ];
            if ($existing) {
                $record->id = $existing->id;
                $DB->update_record('videopractice_stagegrades', $record);
            } else {
                $DB->insert_record('videopractice_stagegrades', $record);
            }
        }
        $grade = $max > 0 ? ($total / $max) * max(0.0, (float)$activity->grade) : 0.0;
        $submission->status = self::STATUS_GRADED;
        $submission->grade = $grade;
        $submission->feedback = (string)($data->feedback ?? '');
        $submission->feedbackformat = FORMAT_HTML;
        $submission->graderid = $graderid;
        $submission->timegraded = time();
        $submission->timemodified = time();
        $DB->update_record('videopractice_submissions', $submission);
        \videopractice_update_grades($activity, (int)$submission->userid, true);
        return $grade;
    }

    /**
     * Reopens a submitted or graded attempt for editing.
     *
     * @param stdClass $activity Activity.
     * @param stdClass $submission Submission.
     * @return void
     */
    public function reopen(stdClass $activity, stdClass $submission): void {
        global $DB;
        $submission->status = self::STATUS_DRAFT;
        $submission->grade = null;
        $submission->graderid = null;
        $submission->timegraded = 0;
        $submission->timemodified = time();
        $DB->update_record('videopractice_submissions', $submission);
        $DB->delete_records('videopractice_stagegrades', ['submissionid' => $submission->id]);
        \videopractice_update_grades($activity, (int)$submission->userid, true);
    }

    /**
     * Touches a draft submission.
     *
     * @param stdClass $submission Submission.
     * @return void
     */
    private function touch(stdClass $submission): void {
        global $DB;
        $submission->timemodified = time();
        $DB->update_record('videopractice_submissions', $submission);
    }
}
