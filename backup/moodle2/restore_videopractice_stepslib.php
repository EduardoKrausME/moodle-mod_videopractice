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
 * Restore structure for Video Practice.
 *
 * @package   mod_videopractice
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restores the Video Practice activity and user data.
 */
class restore_videopractice_activity_structure_step extends restore_activity_structure_step {
    /**
     * Defines XML paths restored by this step.
     *
     * @return restore_path_element[]
     */
    protected function define_structure(): array {
        $paths = [
            new restore_path_element('videopractice', '/activity/videopractice'),
            new restore_path_element('videopractice_stage', '/activity/videopractice/stages/stage'),
        ];
        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element('videopractice_progress', '/activity/videopractice/progresses/progress');
            $paths[] = new restore_path_element('videopractice_submission', '/activity/videopractice/submissions/submission');
            $paths[] = new restore_path_element(
                'videopractice_stagegrade',
                '/activity/videopractice/submissions/submission/stagegrades/stagegrade'
            );
        }
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restores the main activity record.
     *
     * @param array $data Backup data.
     * @return void
     */
    protected function process_videopractice(array $data): void {
        global $DB;
        $record = (object)$data;
        $oldid = $record->id;
        $record->course = $this->get_courseid();
        $record->timecreated = $this->apply_date_offset($record->timecreated);
        $record->timemodified = $this->apply_date_offset($record->timemodified);
        $newitemid = $DB->insert_record('videopractice', $record);
        $this->apply_activity_instance($newitemid);
        $this->set_mapping('videopractice', $oldid, $newitemid, true);
    }

    /**
     * Restores a practice stage.
     *
     * @param array $data Backup data.
     * @return void
     */
    protected function process_videopractice_stage(array $data): void {
        global $DB;
        $oldid = $data['id'];
        $record = (object)$data;
        $record->videopracticeid = $this->get_new_parentid('videopractice');
        $record->timecreated = $this->apply_date_offset($record->timecreated);
        $record->timemodified = $this->apply_date_offset($record->timemodified);
        $newitemid = $DB->insert_record('videopractice_stages', $record);
        $this->set_mapping('videopractice_stage', $oldid, $newitemid);
    }

    /**
     * Restores reference-video progress.
     *
     * @param array $data Backup data.
     * @return void
     */
    protected function process_videopractice_progress(array $data): void {
        global $DB;
        $record = (object)$data;
        $record->videopracticeid = $this->get_new_parentid('videopractice');
        $record->userid = $this->get_mappingid('user', $record->userid, 0);
        if (!$record->userid) {
            return;
        }
        $record->timecreated = $this->apply_date_offset($record->timecreated);
        $record->timemodified = $this->apply_date_offset($record->timemodified);
        $DB->insert_record('videopractice_progress', $record);
    }

    /**
     * Restores a practice submission.
     *
     * @param array $data Backup data.
     * @return void
     */
    protected function process_videopractice_submission(array $data): void {
        global $DB;
        $oldid = $data['id'];
        $record = (object)$data;
        $record->videopracticeid = $this->get_new_parentid('videopractice');
        $record->userid = $this->get_mappingid('user', $record->userid, 0);
        if (!$record->userid) {
            return;
        }
        $record->graderid = empty($record->graderid) ? null : $this->get_mappingid('user', $record->graderid, null);
        $record->timesubmitted = $record->timesubmitted ? $this->apply_date_offset($record->timesubmitted) : 0;
        $record->timegraded = $record->timegraded ? $this->apply_date_offset($record->timegraded) : 0;
        $record->timecreated = $this->apply_date_offset($record->timecreated);
        $record->timemodified = $this->apply_date_offset($record->timemodified);
        $newitemid = $DB->insert_record('videopractice_submissions', $record);
        $this->set_mapping('videopractice_submission', $oldid, $newitemid, true);
    }

    /**
     * Restores per-stage grades and feedback.
     *
     * @param array $data Backup data.
     * @return void
     */
    protected function process_videopractice_stagegrade(array $data): void {
        global $DB;
        $record = (object)$data;
        $record->submissionid = $this->get_new_parentid('videopractice_submission');
        $record->stageid = $this->get_mappingid('videopractice_stage', $record->stageid, 0);
        if (!$record->stageid) {
            return;
        }
        $record->timemodified = $this->apply_date_offset($record->timemodified);
        $DB->insert_record('videopractice_stagegrades', $record);
    }

    /**
     * Restores reference and practice video files after database records exist.
     *
     * @return void
     */
    protected function after_execute(): void {
        $this->add_related_files('mod_videopractice', 'referencevideo', null);
        $this->add_related_files('mod_videopractice', 'submission', 'videopractice_submission');
    }
}
