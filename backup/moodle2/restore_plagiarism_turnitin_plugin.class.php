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
        $elepath = $this->get_pathfor('turnitin_configs/turnitin_config'); // we used get_recommended_name() so this works
        $paths[] = new restore_path_element($elename, $elepath);

        return $paths; // And we return the interesting paths
    }

    public function process_turnitinconfig($data) {
        global $DB;

        $data = (object)$data;

        if ($this->task->is_samesite()) { //files can only be restored if this is the same site as was backed up.
            //check to see if this plagiarism id already exists in a different course.
            $recexists = $DB->record_exists_select('config_plugins', "plugin = ? AND ".$DB->sql_compare_text('value', 10). " = ?", array($data->plugin, $data->value));
            if ($data->name == $this->task->get_courseid() || !$recexists) {
                //only restore if a link to this course doesn't already exist in this install.
                $this->existingcourse = false;
                set_config($this->task->get_courseid(), $data->value, $data->plugin);
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
        $elepath = $this->get_pathfor('turnitin_configs/turnitin_config'); // we used get_recommended_name() so this works
        $paths[] = new restore_path_element($elename, $elepath);

        $elename = 'turnitinfiles';
        $elepath = $this->get_pathfor('/turnitin_files/turnitin_file'); // we used get_recommended_name() so this works
        $paths[] = new restore_path_element($elename, $elepath);

        return $paths; // And we return the interesting paths

    }

    public function process_turnitinconfigmod($data) {
        global $DB;

        if ($this->task->is_samesite() && !$this->existingcourse) { //files can only be restored if this is the same site as was backed up.
            $recexists = false;
            if (! is_object($data)) {
                $data = (object) $data;
            }
            if ($data->name == 'turnitin_assignid') { //check if this assignid already exists
                $recexists = $DB->record_exists('plagiarism_turnitin_config', array('name'=>'turnitin_assignid', 'value' => $data->value));
            }
            if (!$recexists) {
                $data = (object)$data;
                $oldid = $data->id;
                $data->cm = $this->task->get_moduleid();

                $DB->insert_record('plagiarism_turnitin_config', $data);
            }
        }
    }

    public function process_turnitinfiles($data) {
        global $DB;

        if ($this->task->is_samesite() && !$this->existingcourse) { //files can only be restored if this is the same site as was backed up.
            $data = (object)$data;
            $recexists = false;
            if (!empty($data->externalid)) {
                $recexists = $DB->record_exists('plagiarism_turnitin_files', array('externalid'=>$data->externalid));
            }
            if (!$recexists) { //only restore this record if one doesn't exist for this externalid.
                $oldid = $data->id;
                $data->cm = $this->task->get_moduleid();
                $data->userid = $this->get_mappingid('user', $data->userid);

                $DB->insert_record('plagiarism_turnitin_files', $data);
            }
        }
    }
}