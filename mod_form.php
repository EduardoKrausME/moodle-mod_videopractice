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
 * Activity settings form for Video Practice.
 *
 * @package   mod_videopractice
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_videopractice\player;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Video Practice module form.
 */
class mod_videopractice_mod_form extends moodleform_mod {
    /**
     * Defines form controls.
     *
     * @return void
     */
    public function definition(): void {
        global $COURSE;
        $mform = $this->_form;
        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('videopracticename', 'videopractice'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $this->standard_intro_elements();

        $mform->addElement('html', '<h3>' . get_string('referenceheader', 'videopractice') . '</h3>');
        $mform->addElement('select', 'referencesource', get_string('referencesource', 'videopractice'),
            player::source_options());
        $mform->setDefault('referencesource', 'upload');
        $mform->setType('referencesource', PARAM_ALPHA);
        $mform->addElement('filemanager', 'referencevideo', get_string('referencevideo', 'videopractice'), null, [
            'subdirs' => 0,
            'accepted_types' => ['video'],
        ]);
        $mform->hideIf('referencevideo', 'referencesource', 'neq', 'upload');
        $mform->addElement('url', 'referenceurl', get_string('referenceurl', 'videopractice'),
            ['size' => 80], ['usefilepicker' => false]);
        $mform->setType('referenceurl', PARAM_URL);
        $mform->hideIf('referenceurl', 'referencesource', 'eq', 'upload');
        $mform->addHelpButton('referenceurl', 'referenceurl', 'videopractice');

        $mform->addElement('selectyesno', 'resumeplayback', get_string('resumeplayback', 'videopractice'));
        $mform->setDefault('resumeplayback', 1);
        $mform->addElement('selectyesno', 'allowseek', get_string('allowseek', 'videopractice'));
        $mform->setDefault('allowseek', 1);

        $mform->addElement('html', '<h3>' . get_string('submissionheader', 'videopractice') . '</h3>');
        $attemptoptions = [0 => get_string('unlimited', 'videopractice')];
        for ($i = 1; $i <= 10; $i++) {
            $attemptoptions[$i] = (string)$i;
        }
        $mform->addElement('select', 'maxattempts', get_string('maxattempts', 'videopractice'), $attemptoptions);
        $mform->setDefault('maxattempts', 1);
        $mform->addElement('selectyesno', 'allowrecording', get_string('allowrecording', 'videopractice'));
        $mform->setDefault('allowrecording', 1);
        $maxbytes = get_max_upload_sizes($CFG->maxbytes, $COURSE->maxbytes);
        $mform->addElement('select', 'submissionmaxbytes', get_string('submissionmaxbytes', 'videopractice'), $maxbytes);
        $mform->setDefault('submissionmaxbytes', 0);

        $this->standard_grading_coursemodule_elements();
        $mform->setDefault('grade', 100);
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Validates source data and completion percentage.
     *
     * @param array $data Form data.
     * @param array $files Files.
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $sourceerror = player::validate_source((array)$data);
        if ($sourceerror !== '') {
            $field = ($data['referencesource'] ?? '') === 'upload' ? 'referencevideo' : 'referenceurl';
            $errors[$field] = $sourceerror;
        }
        $field = $this->completion_name('completionpercent');
        if (isset($data[$field])) {
            $value = (int)$data[$field];
            if ($value < 1 || $value > 100) {
                $errors[$field] = get_string('errorcompletionpercent', 'videopractice');
            }
        }
        foreach (['referencevideo'] as $field) {
            $draftid = (int)($data[$field] ?? 0);
            if ($draftid > 0) {
                $draftinfo = file_get_draft_area_info($draftid);
                if ((int)$draftinfo['filecount'] > 1) {
                    $errors[$field] = get_string('errormaxfiles', 'videopractice');
                }
            }
        }
        return $errors;
    }

    /**
     * Prepares protected reference file draft area.
     *
     * @param array $defaultvalues Default form values.
     * @return void
     */
    public function data_preprocessing(&$defaultvalues): void {
        foreach (['completionpercent', 'completionrequirepractice'] as $field) {
            if (array_key_exists($field, $defaultvalues)) {
                $defaultvalues[$this->completion_name($field)] = $defaultvalues[$field];
            }
        }
        if (!empty($this->current->instance)) {
            player::prepare_form_data($defaultvalues, $this->context);
        }
    }

    /**
     * Adds custom completion rules.
     *
     * @return array
     */
    public function add_completion_rules(): array {
        $mform = $this->_form;
        $percent = $this->completion_name('completionpercent');
        $practice = $this->completion_name('completionrequirepractice');
        $mform->addElement('text', $percent, get_string('completionpercent', 'videopractice'), ['size' => 5]);
        $mform->setType($percent, PARAM_INT);
        $mform->setDefault($percent, 80);
        $mform->addRule($percent, null, 'numeric', null, 'client');
        $mform->addElement('selectyesno', $practice, get_string('completionrequirepractice', 'videopractice'));
        $mform->setDefault($practice, 1);
        return [$percent, $practice];
    }

    /**
     * Whether at least one custom completion rule is active.
     *
     * @param stdClass $data Form data.
     * @return bool
     */
    public function completion_rule_enabled($data): bool {
        return !empty($data[$this->completion_name('completionpercent')]) ||
            !empty($data[$this->completion_name('completionrequirepractice')]);
    }

    /**
     * Maps suffixed completion values back to DB field names.
     *
     * @return stdClass|null
     */
    public function get_data() {
        $data = parent::get_data();
        if (!$data) {
            return $data;
        }
        foreach (['completionpercent', 'completionrequirepractice'] as $field) {
            $suffixed = $this->completion_name($field);
            if (property_exists($data, $suffixed)) {
                $data->{$field} = $data->{$suffixed};
                unset($data->{$suffixed});
            }
        }
        return $data;
    }

    /**
     * Returns Moodle-safe completion form field name.
     *
     * @param string $field DB field.
     * @return string
     */
    private function completion_name(string $field): string {
        return $field . '_videopractice';
    }
}
