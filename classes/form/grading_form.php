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
 * Teacher assessment form.
 *
 * @package   mod_videopractice
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videopractice\form;

defined('MOODLE_INTERNAL') || die();

require_once("{$CFG->libdir}/formslib.php");

/**
 * Scores each practice stage separately.
 */
class grading_form extends \moodleform {
    /**
     * Defines dynamic stage controls.
     *
     * @return void
     */
    public function definition(): void {
        $stages = $this->_customdata['stages'];
        $mform = $this->_form;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'submissionid');
        $mform->setType('submissionid', PARAM_INT);
        foreach ($stages as $stage) {
            $mform->addElement('header', 'stageheader_' . $stage->id, format_string($stage->name));
            if (trim((string)$stage->instructions) !== '') {
                $mform->addElement('static', 'instructions_' . $stage->id,
                    get_string('stageinstructions', 'videopractice'), format_text($stage->instructions, FORMAT_HTML));
            }
            $mform->addElement('text', 'score_' . $stage->id,
                get_string('stagescore', 'videopractice', format_float($stage->maxscore, 2)), ['size' => 8]);
            $mform->setType('score_' . $stage->id, PARAM_FLOAT);
            $mform->addRule('score_' . $stage->id, null, 'required', null, 'client');
            $mform->addRule('score_' . $stage->id, null, 'numeric', null, 'client');
            $mform->addElement('textarea', 'feedback_' . $stage->id,
                get_string('stagefeedback', 'videopractice'), ['rows' => 3, 'cols' => 70]);
            $mform->setType('feedback_' . $stage->id, PARAM_RAW);
        }
        $mform->addElement('html', '<h3>' . get_string('overallfeedback', 'videopractice') . '</h3>');
        $mform->addElement('textarea', 'feedback', get_string('overallfeedback', 'videopractice'),
            ['rows' => 5, 'cols' => 70]);
        $mform->setType('feedback', PARAM_RAW);
        $this->add_action_buttons(true, get_string('saveassessment', 'videopractice'));
    }

    /**
     * Validates stage score ranges.
     *
     * @param array $data Data.
     * @param array $files Files.
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        foreach ($this->_customdata['stages'] as $stage) {
            $field = 'score_' . $stage->id;
            if (isset($data[$field]) && ((float)$data[$field] < 0 || (float)$data[$field] > (float)$stage->maxscore)) {
                $errors[$field] = get_string('errorscorerange', 'videopractice', format_float($stage->maxscore, 2));
            }
        }
        return $errors;
    }
}
