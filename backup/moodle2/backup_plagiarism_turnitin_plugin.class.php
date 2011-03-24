<?php

defined('MOODLE_INTERNAL') || die();


class backup_plagiarism_turnitin_plugin extends backup_plagiarism_plugin {
    function define_module_plugin_structure() {
        // To know if we are including userinfo
        $userinfo = $this->get_setting_value('userinfo');

        // Define the virtual plugin element without conditions as the global class checks already.
        $plugin = $this->get_plugin_element();

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
        // Define the virtual plugin element without conditions as the global class checks already.
        $plugin = $this->get_plugin_element();

        // Create one standard named plugin element (the visible container)
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());

        // connect the visible container ASAP
        $plugin->add_child($pluginwrapper);
        //save id from turnitin course
        $turnitinconfigs = new backup_nested_element('turnitin_configs');
        $turnitinconfig = new backup_nested_element('turnitin_config', array('id'), array('plugin', 'name', 'value'));
        $pluginwrapper->add_child($turnitinconfigs);
        $turnitinconfigs->add_child($turnitinconfig);
        $turnitinconfig->set_source_table('config_plugins', array('name'=> backup::VAR_PARENTID, 'plugin' => backup_helper::is_sqlparam('plagiarism_turnitin_course')));
        return $plugin;
    }
}