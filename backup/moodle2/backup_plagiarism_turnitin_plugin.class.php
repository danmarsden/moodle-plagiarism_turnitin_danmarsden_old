<?php

defined('MOODLE_INTERNAL') || die();


class backup_plagiarism_turnitin_plugin extends backup_plagiarism_plugin {
    function define_module_plugin_structure() {
        // To know if we are including userinfo
        $userinfo = $this->get_setting_value('userinfo');

        // Define the virtual plugin element with the condition to fulfill
        // Note: we use $this->pluginname so for extended plugins this will work
        // automatically: calculatedsimple and calculatedmulti
        $plugin = $this->get_plugin_element(null, $this->check_turnitin_enabled(), 'turnitin');

        // Create one standard named plugin element (the visible container)
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());

        // connect the visible container ASAP
        $plugin->add_child($pluginwrapper);

        $turnitinconfigs = new backup_nested_element('turnitin_configs');
        $turnitinconfig = new backup_nested_element('turnitin_config', array('id'), array('name', 'value'));
        $pluginwrapper->add_child($turnitinconfigs);
        $turnitinconfigs->add_child($turnitinconfig);
        $turnitinconfig->set_source_table('turnitin_config', array('cm' => backup::VAR_PARENTID));

        //now information about files to module
        $turnitinfiles = new backup_nested_element('turnitin_files');
        $turnitinfile = new backup_nested_element('turnitin_file', array('id'),
                            array('userid', 'identifier','externalid','externalstatus','statuscode','similarityscore'));

        $pluginwrapper->add_child($turnitinfiles);
        $turnitinfiles->add_child($turnitinfile);
        if ($userinfo) {
            $turnitinfile->set_source_table('turnitin_files', array('cm' => backup::VAR_PARENTID));
        }
        return $plugin;
    }

    function define_course_plugin_structure() {
        // Define the virtual plugin element with the condition to fulfill
        // Note: we use $this->pluginname so for extended plugins this will work
        // automatically: calculatedsimple and calculatedmulti
        $plugin = $this->get_plugin_element(null, $this->check_turnitin_enabled(), 'turnitin');

        // Create one standard named plugin element (the visible container)
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());

        // connect the visible container ASAP
        $plugin->add_child($pluginwrapper);
        //TODO: save id from turnitin course
        $turnitinconfigs = new backup_nested_element('turnitin_configs');
        $turnitinconfig = new backup_nested_element('turnitin_config', array('id'), array('name', 'value'));
        $pluginwrapper->add_child($turnitinconfigs);
        $turnitinconfigs->add_child($turnitinconfig);
        $turnitinconfig->set_source_array(array('plagiarism_turnitin_course'=>get_config('plagiarism_turnitin_course', backup::VAR_COURSEID)));
        //get_config('plagiarism_turnitin_course', $course->id); //course ID
        return $plugin;
    }
    function check_turnitin_enabled() {
        global $CFG;
        //check this plugin is enabled
        if (!empty($CFG->enableplagiarism) && get_config('plagiarism', "turnitin_use")) {
            return array('sqlparam' => 'turnitin');
        }
        return array();
    }
}