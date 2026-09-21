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
 * AJAX endpoint for reference video progress.
 *
 * @package   mod_videopractice
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videopractice\external;

use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_videopractice\progress_manager;

/**
 * Stores watched reference-video segments.
 */
class update_progress extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'duration' => new external_value(PARAM_FLOAT, 'Video duration'),
            'position' => new external_value(PARAM_FLOAT, 'Current position'),
            'segments' => new external_value(PARAM_RAW, 'JSON watched intervals'),
        ]);
    }

    /**
     * Executes progress update.
     *
     * @param int $cmid Course module id.
     * @param float $duration Duration.
     * @param float $position Position.
     * @param string $segments JSON segments.
     * @return array
     */
    public static function execute(int $cmid, float $duration, float $position, string $segments): array {
        global $DB, $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'duration' => $duration,
            'position' => $position,
            'segments' => $segments,
        ]);
        $cm = get_coursemodule_from_id('videopractice', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/videopractice:view', $context);
        $activity = $DB->get_record('videopractice', ['id' => $cm->instance], '*', MUST_EXIST);
        $decoded = json_decode($params['segments'], true);
        if (!is_array($decoded)) {
            $decoded = [];
        }
        $record = (new progress_manager())->update(
            $activity,
            $cm,
            (int)$USER->id,
            (float)$params['duration'],
            (float)$params['position'],
            $decoded
        );
        return [
            'percent' => (float)$record->percent,
            'completed' => (bool)$record->completed,
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'percent' => new external_value(PARAM_FLOAT, 'Watched percent'),
            'completed' => new external_value(PARAM_BOOL, 'Reference threshold reached'),
        ]);
    }
}
