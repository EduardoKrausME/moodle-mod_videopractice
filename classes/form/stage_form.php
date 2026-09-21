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
 * Practice stage form.
 *
 * @package   mod_videopractice
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videopractice\form;

defined('MOODLE_INTERNAL') || die();

require_once("{$CFG->libdir}/formslib.php");

/**
 * Add/edit stage form.
 */
class stage_form extends \moodleform {
    /**
     * Form definition.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'stageid');
        $mform->setType('stageid', PARAM_INT);
        $mform->addElement('text', 'name', get_string('stagename', 'videopractice'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addElement('textarea', 'instructions', get_string('stageinstructions', 'videopractice'),
            ['rows' => 8, 'cols' => 70]);
        $mform->setType('instructions', PARAM_RAW);
        $mform->addElement('text', 'maxscore', get_string('stagemaxscore', 'videopractice'), ['size' => 8]);
        $mform->setType('maxscore', PARAM_FLOAT);
        $mform->setDefault('maxscore', 100);
        $mform->addRule('maxscore', null, 'numeric', null, 'client');
        $this->add_action_buttons();
    }

    /**
     * Validates stage score.
     *
     * @param array $data Data.
     * @param array $files Files.
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if ((float)($data['maxscore'] ?? 0) <= 0) {
            $errors['maxscore'] = get_string('errormaxscore', 'videopractice');
        }
        return $errors;
    }
}
