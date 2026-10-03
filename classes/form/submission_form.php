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
 * Student video submission form.
 *
 * @package   mod_videopractice
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videopractice\form;

defined('MOODLE_INTERNAL') || die;

require_once("{$CFG->libdir}/formslib.php");

/**
 * File upload form for a practice attempt.
 */
class submission_form extends \moodleform {
    /**
     * Form definition.
     *
     * @return void
     */
    public function definition(): void {
        $activity = $this->_customdata['activity'];
        $mform = $this->_form;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('filemanager', 'practicevideo', get_string('practicevideo', 'videopractice'), null, [
            'subdirs' => 0,
            'maxbytes' => (int)$activity->submissionmaxbytes,
            'accepted_types' => ['video'],
        ]);
        $mform->addRule('practicevideo', get_string('practicevideorequired', 'videopractice'), 'required', null, 'client');
        $buttons = [];
        $buttons[] = $mform->createElement('submit', 'savedraft', get_string('savedraft', 'videopractice'));
        $buttons[] = $mform->createElement('submit', 'submitpractice', get_string('submitpractice', 'videopractice'),
            ['class' => 'btn-primary']);
        $buttons[] = $mform->createElement('cancel');
        $mform->addGroup($buttons, 'buttonar', '', [' '], false);
        $mform->closeHeaderBefore('buttonar');
    }
    /**
     * Validates the uploaded practice video.
     *
     * @param array $data Submitted form data.
     * @param array $files Submitted files.
     * @return array Validation errors.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $draftid = (int)($data['practicevideo'] ?? 0);
        if ($draftid > 0) {
            $draftinfo = file_get_draft_area_info($draftid);
            if ((int)$draftinfo['filecount'] > 1) {
                $errors['practicevideo'] = get_string('errormaxfiles', 'videopractice');
            }
        }
        return $errors;
    }

}
