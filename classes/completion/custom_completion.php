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
 * Custom completion rules for Video Practice.
 *
 * @package   mod_videopractice
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videopractice\completion;

use core_completion\activity_custom_completion;
use mod_videopractice\submission_manager;

/**
 * Completion based on reference watching and practice submission.
 */
class custom_completion extends activity_custom_completion {
    /**
     * Returns state of one custom rule.
     *
     * Availability is resolved by get_available_custom_rules() before Moodle calls
     * this method. Re-validating availability here can produce a false negative when
     * cm_info changes or is rebuilt between the two calls, so only the rule definition
     * itself is validated here.
     *
     * @param string $rule Rule name.
     * @return int
     */
    public function get_state(string $rule): int {
        global $DB;

        if (!$this->is_defined($rule)) {
            throw new \coding_exception("Undefined custom completion rule '{$rule}'");
        }

        $activity = $DB->get_record('videopractice', ['id' => $this->cm->instance], '*', MUST_EXIST);
        if ($rule === 'completionpercent') {
            $progress = $DB->get_record('videopractice_progress', [
                'videopracticeid' => $activity->id,
                'userid' => $this->userid,
            ]);
            return $progress && (float)$progress->percent >= (float)$activity->completionpercent
                ? COMPLETION_COMPLETE
                : COMPLETION_INCOMPLETE;
        }
        if ($rule === 'completionrequirepractice') {
            $submission = (new submission_manager())->get_latest((int)$activity->id, (int)$this->userid);
            return $submission && in_array($submission->status, [
                submission_manager::STATUS_SUBMITTED,
                submission_manager::STATUS_GRADED,
            ], true) ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
        }
        return COMPLETION_INCOMPLETE;
    }

    /**
     * Returns custom rule names.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return ['completionpercent', 'completionrequirepractice'];
    }

    /**
     * Returns the custom completion rules enabled for this activity instance.
     *
     * The persisted activity settings are the source of truth. cm_info custom data may be
     * temporarily empty or incomplete while caches are rebuilt.
     *
     * @return string[]
     */
    public function get_available_custom_rules(): array {
        if ((int)$this->cm->completion !== COMPLETION_TRACKING_AUTOMATIC) {
            return [];
        }

        global $DB;

        $activity = $DB->get_record(
            'videopractice',
            ['id' => $this->cm->instance],
            'id,completionpercent,completionrequirepractice',
            MUST_EXIST
        );

        $rules = [];
        if ((int)$activity->completionpercent > 0) {
            $rules[] = 'completionpercent';
        }
        if (!empty($activity->completionrequirepractice)) {
            $rules[] = 'completionrequirepractice';
        }

        return $rules;
    }

    /**
     * Human-readable descriptions.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        global $DB;
        $activity = $DB->get_record('videopractice', ['id' => $this->cm->instance], '*', MUST_EXIST);
        $descriptions = [
            'completionpercent' => get_string('completionpercentdesc', 'videopractice', (int)$activity->completionpercent),
        ];
        if (!empty($activity->completionrequirepractice)) {
            $descriptions['completionrequirepractice'] = get_string('completionrequirepracticedesc', 'videopractice');
        }
        return $descriptions;
    }

    /**
     * Returns completion rules in display order.
     *
     * @return string[]
     */
    public function get_sort_order(): array {
        return [
            'completionview',
            'completionpercent',
            'completionrequirepractice',
            'completionusegrade',
            'completionpassgrade',
        ];
    }
}
