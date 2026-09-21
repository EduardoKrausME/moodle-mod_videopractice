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
 * Stage manager.
 *
 * @package   mod_videopractice
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videopractice;

use stdClass;

/**
 * CRUD and ordering operations for practice stages.
 */
class stage_manager {
    /**
     * Returns ordered stages.
     *
     * @param int $activityid Activity id.
     * @return array
     */
    public function get_all(int $activityid): array {
        global $DB;
        return array_values($DB->get_records('videopractice_stages', [
            'videopracticeid' => $activityid,
        ], 'sortorder ASC, id ASC'));
    }

    /**
     * Creates the default practice stage.
     *
     * @param int $activityid Activity id.
     * @return int
     */
    public function create_default(int $activityid): int {
        global $DB;
        $now = time();
        return $DB->insert_record('videopractice_stages', (object)[
            'videopracticeid' => $activityid,
            'name' => get_string('defaultstage', 'videopractice'),
            'instructions' => '',
            'maxscore' => 100,
            'sortorder' => 10,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Moves a stage one position.
     *
     * @param int $activityid Activity id.
     * @param int $stageid Stage id.
     * @param int $direction -1 up, 1 down.
     * @return void
     */
    public function move(int $activityid, int $stageid, int $direction): void {
        global $DB;
        $stages = $this->get_all($activityid);
        $index = null;
        foreach ($stages as $key => $stage) {
            if ((int)$stage->id === $stageid) {
                $index = $key;
                break;
            }
        }
        if ($index === null) {
            return;
        }
        $target = $index + ($direction < 0 ? -1 : 1);
        if (!isset($stages[$target])) {
            return;
        }
        $current = $stages[$index];
        $other = $stages[$target];
        $currentsort = $current->sortorder;
        $current->sortorder = $other->sortorder;
        $other->sortorder = $currentsort;
        $current->timemodified = time();
        $other->timemodified = time();
        $DB->update_record('videopractice_stages', $current);
        $DB->update_record('videopractice_stages', $other);
    }
}
