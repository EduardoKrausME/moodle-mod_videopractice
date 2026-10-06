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
 * Backup structure for Video Practice.
 *
 * @package   mod_videopractice
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Builds the activity XML used by Moodle backup.
 */
class backup_videopractice_activity_structure_step extends backup_activity_structure_step {
    /**
     * Defines the Video Practice backup tree.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $videopractice = new backup_nested_element('videopractice', ['id'], [
            'name', 'intro', 'introformat', 'referencesource', 'referenceurl', 'resumeplayback',
            'allowseek', 'completionpercent', 'completionrequirepractice', 'maxattempts',
            'allowrecording', 'submissionmaxbytes', 'grade', 'timecreated', 'timemodified',
        ]);
        $stages = new backup_nested_element('stages');
        $stage = new backup_nested_element('stage', ['id'], [
            'name', 'instructions', 'maxscore', 'sortorder', 'timecreated', 'timemodified',
        ]);
        $progresses = new backup_nested_element('progresses');
        $progress = new backup_nested_element('progress', ['id'], [
            'userid', 'duration', 'lastposition', 'watchedsegments', 'percent', 'completed',
            'timecreated', 'timemodified',
        ]);
        $submissions = new backup_nested_element('submissions');
        $submission = new backup_nested_element('submission', ['id'], [
            'userid', 'attemptnumber', 'status', 'timesubmitted', 'grade', 'feedback', 'feedbackformat',
            'graderid', 'timegraded', 'timecreated', 'timemodified',
        ]);
        $stagegrades = new backup_nested_element('stagegrades');
        $stagegrade = new backup_nested_element('stagegrade', ['id'], [
            'stageid', 'score', 'feedback', 'feedbackformat', 'timemodified',
        ]);

        $videopractice->add_child($stages);
        $stages->add_child($stage);
        $videopractice->add_child($progresses);
        $progresses->add_child($progress);
        $videopractice->add_child($submissions);
        $submissions->add_child($submission);
        $submission->add_child($stagegrades);
        $stagegrades->add_child($stagegrade);

        $videopractice->set_source_table('videopractice', ['id' => backup::VAR_ACTIVITYID]);
        $stage->set_source_table('videopractice_stages', ['videopracticeid' => backup::VAR_PARENTID]);
        if ($userinfo) {
            $progress->set_source_table('videopractice_progress', ['videopracticeid' => backup::VAR_PARENTID]);
            $submission->set_source_table('videopractice_submissions', ['videopracticeid' => backup::VAR_PARENTID]);
            $stagegrade->set_source_table('videopractice_stagegrades', ['submissionid' => backup::VAR_PARENTID]);
        }

        $progress->annotate_ids('user', 'userid');
        $submission->annotate_ids('user', 'userid');
        $submission->annotate_ids('user', 'graderid');
        $stagegrade->annotate_ids('videopractice_stage', 'stageid');

        $videopractice->annotate_files('mod_videopractice', 'intro', null);
        $videopractice->annotate_files('mod_videopractice', 'referencevideo', null);
        $submission->annotate_files('mod_videopractice', 'submission', 'id');

        return $this->prepare_activity_structure($videopractice);
    }
}
