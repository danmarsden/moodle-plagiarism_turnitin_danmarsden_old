<?php

require_once($CFG->dirroot.'/lib/formslib.php');

class turnitin_setup_form extends moodleform {

/// Define the form
    function definition () {
        global $CFG;

        $mform =& $this->_form;
        $choices = array('No','Yes');
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

        /*$mform->addElement('text', 'turnitin_emailprefix', get_string('tiiemailprefix', 'plagiarism_turnitin'));
        $mform->addHelpButton('turnitin_emailprefix', 'tiiemailprefix', 'plagiarism_turnitin');
        $mform->disabledIf('turnitin_emailprefix', 'turnitin_senduseremail', 'checked');*/

        $mform->addElement('text', 'turnitin_courseprefix', get_string('tiicourseprefix', 'plagiarism_turnitin'));
        $mform->addHelpButton('turnitin_courseprefix', 'tiicourseprefix', 'plagiarism_turnitin');
        $mform->addRule('turnitin_courseprefix', null, 'required', null, 'client');

        $mform->addElement('textarea', 'turnitin_student_disclosure', get_string('studentdisclosure','plagiarism_turnitin'),'wrap="virtual" rows="6" cols="50"');
        $mform->addHelpButton('turnitin_student_disclosure', 'studentdisclosure', 'plagiarism_turnitin');
        $mform->setDefault('turnitin_student_disclosure', get_string('studentdisclosuredefault','plagiarism_turnitin'));

        $this->add_action_buttons(true);
    }
}

class turnitin_defaults_form extends moodleform {

/// Define the form
    function definition () {
        $mform =& $this->_form;
        turnitin_get_form_elements($mform);
        $this->add_action_buttons(true);
    }
}

