<?php

defined('MOODLE_INTERNAL') || die();


class restore_plagiarism_turnitin_plugin extends restore_plagiarism_plugin {
    protected $existingcourse;
    /**
     * Returns the paths to be handled by the plugin at question level
     */
    protected function define_course_plugin_structure() {
        $paths = array();

        // Add own format stuff
        $elename = 'turnitinconfig';
        $elepath = $this->get_pathfor('turnitin_configs'); // we used get_recommended_name() so this works
        $paths[] = new restore_path_element($elename, $elepath);

        return $paths; // And we return the interesting paths
    }

    public function process_turnitinconfig($data) {
        global $DB;

        $data = (object)$data;

        if ($this->task->is_samesite()) { //files can only be restored if this is the same site as was backed up.
            //check to see if this plagiarism id already exists in a different course.
            if (!$DB->record_exists('config_plugins', array('plugin'=>$data->plugin, 'value'=>$data->value))) {
                //only restore if a link to this course doesn't already exist in this install.
                $this->existingcourse = false;
                set_config($this->task->get_moduleid(), $data->value, $data->plugin);
            } else {
                $this->existingcourse = true;
            }

        }
    }

    /**
     * Returns the paths to be handled by the plugin at module level
     */
    protected function define_module_plugin_structure() {
        $paths = array();

        // Add own format stuff
        $elename = 'turnitinconfigmod';
        $elepath = $this->get_pathfor('turnitin_configs'); // we used get_recommended_name() so this works
        $paths[] = new restore_path_element($elename, $elepath);

        $elename = 'turnitinfiles';
        $elepath = $this->get_pathfor('/turnitin_files'); // we used get_recommended_name() so this works
        $paths[] = new restore_path_element($elename, $elepath);

        return $paths; // And we return the interesting paths

    }

    public function process_turnitinconfigmod($data) {
        global $DB;
        if ($this->task->is_samesite() && !$this->existingcourse) { //files can only be restored if this is the same site as was backed up.
//todo: add check to see if this data already exists in another course.
            $data = (object)$data;
            $oldid = $data->id;
            $data->cm = $this->task->get_moduleid();

            $DB->insert_record('turnitin_config', $data);
        }
    }

    public function process_turnitinfiles($data) {
        global $DB;
        if ($this->task->is_samesite() && !$this->existingcourse) { //files can only be restored if this is the same site as was backed up.
//todo: add check to see if this data already exists in another course.
            $data = (object)$data;
            $oldid = $data->id;
            $data->cm = $this->task->get_moduleid();
            $data->userid = $this->get_mappingid('user', $data->userid);

            $DB->insert_record('turnitin_files', $data);
        }
    }
}