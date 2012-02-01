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

require_once($CFG->dirroot.'/lib/formslib.php');

class turnitin_setup_form extends moodleform {

    /// Define the form
    public function definition () {
        global $CFG;

        $mform =& $this->_form;
        $choices = array('No', 'Yes');
        $mform->addElement('html', get_string('tiiexplain', 'plagiarism_turnitin'));
        $mform->addElement('checkbox', 'turnitin_use', get_string('useturnitin', 'plagiarism_turnitin'));

        $mform->addElement('text', 'turnitin_api', get_string('tiiapi', 'plagiarism_turnitin'));
        $mform->addHelpButton('turnitin_api', 'tiiapi', 'plagiarism_turnitin');
        $mform->addRule('turnitin_api', null, 'required', null, 'client');
        $mform->setDefault('turnitin_api', 'https://api.turnitin.com/api.asp');

        $mform->addElement('text', 'turnitin_accountid', get_string('tiiaccountid', 'plagiarism_turnitin'));
        $mform->addHelpButton('turnitin_accountid', 'tiiaccountid', 'plagiarism_turnitin');
        $mform->addRule('turnitin_accountid', null, 'numeric', null, 'client');

        $mform->addElement('passwordunmask', 'turnitin_secretkey', get_string('tiisecretkey', 'plagiarism_turnitin'));
        $mform->addHelpButton('turnitin_secretkey', 'tiisecretkey', 'plagiarism_turnitin');
        $mform->addRule('turnitin_secretkey', null, 'required', null, 'client');

        $mform->addElement('checkbox', 'turnitin_enablegrademark', get_string('tiienablegrademark', 'plagiarism_turnitin'));
        $mform->addHelpButton('turnitin_enablegrademark', 'tiienablegrademark', 'plagiarism_turnitin');

        $mform->addElement('checkbox', 'turnitin_institutionnode', get_string('turnitin_institutionnode', 'plagiarism_turnitin'));
        $mform->addHelpButton('turnitin_institutionnode', 'turnitin_institutionnode', 'plagiarism_turnitin');

        $mform->addElement('checkbox', 'turnitin_senduseremail', get_string('tiisenduseremail', 'plagiarism_turnitin'));
        $mform->addHelpButton('turnitin_senduseremail', 'tiisenduseremail', 'plagiarism_turnitin');

        $mform->addElement('text', 'turnitin_attemptcodes', get_string('turnitin_attemptcodes', 'plagiarism_turnitin'));
        $mform->addHelpButton('turnitin_attemptcodes', 'turnitin_attemptcodes', 'plagiarism_turnitin');
        $mform->setDefault('turnitin_attemptcodes', '1009,1013,1023');

        $mform->addElement('text', 'turnitin_attempts', get_string('turnitin_attempts', 'plagiarism_turnitin'));
        $mform->addHelpButton('turnitin_attempts', 'turnitin_attempts', 'plagiarism_turnitin');
        $mform->addRule('turnitin_attempts', null, 'numeric', null, 'client');
        $mform->addRule('turnitin_attempts', null, 'maxlength', 1, 'client');
        $mform->setDefault('turnitin_attempts', '1');

        $mform->addElement('textarea', 'turnitin_student_disclosure', get_string('studentdisclosure', 'plagiarism_turnitin'), 'wrap="virtual" rows="6" cols="50"');
        $mform->addHelpButton('turnitin_student_disclosure', 'studentdisclosure', 'plagiarism_turnitin');
        $mform->setDefault('turnitin_student_disclosure', get_string('studentdisclosuredefault', 'plagiarism_turnitin'));

        $this->add_action_buttons(true);
    }
}

class turnitin_defaults_form extends moodleform {

    /// Define the form
    public function definition () {
        $mform =& $this->_form;
        turnitin_get_form_elements($mform);
        $this->add_action_buttons(true);
    }
}

