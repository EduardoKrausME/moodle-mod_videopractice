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
 * Reference video progress manager.
 *
 * @package   mod_videopractice
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videopractice;

use completion_info;
use stdClass;

/**
 * Stores and calculates watched reference-video intervals.
 */
class progress_manager {
    /**
     * Returns a user's current progress or a transient zero record.
     *
     * @param int $activityid Activity id.
     * @param int $userid User id.
     * @return stdClass
     */
    public function get(int $activityid, int $userid): stdClass {
        global $DB;
        $record = $DB->get_record('videopractice_progress', [
            'videopracticeid' => $activityid,
            'userid' => $userid,
        ]);
        if ($record) {
            return $record;
        }
        return (object)[
            'id' => 0,
            'videopracticeid' => $activityid,
            'userid' => $userid,
            'duration' => 0,
            'lastposition' => 0,
            'watchedsegments' => '[]',
            'percent' => 0,
            'completed' => 0,
            'timecreated' => 0,
            'timemodified' => 0,
        ];
    }

    /**
     * Merges browser-observed segments with previously stored segments and recalculates percentage.
     *
     * @param stdClass $activity Activity record.
     * @param stdClass $cm Course module.
     * @param int $userid User id.
     * @param float $duration Video duration.
     * @param float $position Last playback position.
     * @param array $segments Watched segment pairs.
     * @return stdClass Updated progress.
     */
    public function update(stdClass $activity, stdClass $cm, int $userid, float $duration,
                           float $position, array $segments): stdClass {
        global $DB;

        $existing = $this->get((int)$activity->id, $userid);
        $oldsegments = json_decode((string)$existing->watchedsegments, true);
        if (!is_array($oldsegments)) {
            $oldsegments = [];
        }
        $duration = max(0.0, min(864000.0, $duration));
        if ((float)$existing->duration > 0) {
            $duration = max((float)$existing->duration, $duration);
        }
        $position = max(0.0, $duration > 0 ? min($duration, $position) : $position);
        $storedsegments = self::merge_segments($oldsegments, $duration);
        $merged = self::merge_segments(array_merge($storedsegments, $segments), $duration);
        $oldwatched = self::watched_seconds($storedsegments);
        $watched = self::watched_seconds($merged);

        // Never trust a browser request that claims more new viewing time than could
        // reasonably have elapsed since the previous server update. This does not
        // rely on a client-provided percentage and prevents instant forged completion.
        $elapsed = $existing->timemodified ? max(1, time() - (int)$existing->timemodified) : 3;
        $maximumgrowth = max(5.0, ($elapsed * 2.0) + 5.0);
        if (($watched - $oldwatched) > $maximumgrowth) {
            $merged = $storedsegments;
            $watched = $oldwatched;
        }

        $percent = $duration > 0 ? min(100.0, ($watched / $duration) * 100.0) : 0.0;
        $completed = $percent >= (float)$activity->completionpercent ? 1 : 0;
        $now = time();

        $record = (object)[
            'videopracticeid' => $activity->id,
            'userid' => $userid,
            'duration' => $duration,
            'lastposition' => $position,
            'watchedsegments' => json_encode($merged),
            'percent' => round($percent, 2),
            'completed' => $completed,
            'timemodified' => $now,
        ];
        if ($existing->id) {
            $record->id = $existing->id;
            $DB->update_record('videopractice_progress', $record);
        } else {
            $record->timecreated = $now;
            $record->id = $DB->insert_record('videopractice_progress', $record);
        }

        $course = $DB->get_record('course', ['id' => $activity->course], '*', MUST_EXIST);
        $completion = new completion_info($course);
        if ($completion->is_enabled($cm) && (int)$cm->completion === COMPLETION_TRACKING_AUTOMATIC) {
            $completion->update_state($cm, COMPLETION_UNKNOWN, $userid);
        }
        return $record;
    }

    /**
     * Sanitises, sorts and merges watched intervals.
     *
     * @param array $segments Segment pairs.
     * @param float $duration Duration used to clamp values.
     * @return array
     */
    public static function merge_segments(array $segments, float $duration = 0): array {
        $normalised = [];
        foreach ($segments as $segment) {
            if (!is_array($segment) || count($segment) < 2 || !is_numeric($segment[0]) || !is_numeric($segment[1])) {
                continue;
            }
            $start = max(0.0, (float)$segment[0]);
            $end = max(0.0, (float)$segment[1]);
            if ($duration > 0) {
                $start = min($duration, $start);
                $end = min($duration, $end);
            }
            if ($end <= $start || ($end - $start) > 120.0) {
                continue;
            }
            $normalised[] = [$start, $end];
        }
        usort($normalised, static fn(array $a, array $b): int => $a[0] <=> $b[0]);
        $merged = [];
        foreach ($normalised as $segment) {
            if (!$merged) {
                $merged[] = $segment;
                continue;
            }
            $last = count($merged) - 1;
            if ($segment[0] <= $merged[$last][1] + 0.75) {
                $merged[$last][1] = max($merged[$last][1], $segment[1]);
            } else {
                $merged[] = $segment;
            }
        }
        return $merged;
    }

    /**
     * Returns the unique watched time represented by merged intervals.
     *
     * @param array $segments Merged watched intervals.
     * @return float
     */
    private static function watched_seconds(array $segments): float {
        $watched = 0.0;
        foreach ($segments as $segment) {
            $watched += max(0.0, (float)$segment[1] - (float)$segment[0]);
        }
        return $watched;
    }

}
