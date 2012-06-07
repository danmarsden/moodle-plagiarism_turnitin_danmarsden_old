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

// This file keeps track of upgrades to
// the plagiarism Turnitin module

/**
 * @global moodle_database $DB
 * @param int $oldversion
 * @return bool
 */
function xmldb_plagiarism_turnitin_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();
    if ($oldversion < 2011041200) {
        $table = new xmldb_table('turnitin_files');
        $field = new xmldb_field('attempt', XMLDB_TYPE_INTEGER, '5', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'similarityscore');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2011041200, 'plagiarism', 'turnitin');
    }

    if ($oldversion < 2011083100) {

        // Define field apimd5 to be added to turnitin_files
        $table = new xmldb_table('turnitin_files');
        $field = new xmldb_field('apimd5', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'attempt');

        // Conditionally launch add field apimd5
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // turnitin savepoint reached
        upgrade_plugin_savepoint(true, 2011083100, 'plagiarism', 'turnitin');
    }

    // Switch all file identifiers to use pathnamehash, not contenthash (which is not unique)
    if ($oldversion < 2011111000) {

        $turnitinfiles = $DB->get_records('turnitin_files');
        $fs = get_file_storage();

        $courseworkid = $DB->get_field('modules', 'id', array('name' => 'coursework'));
        $assignmentid = $DB->get_field('modules', 'id', array('name' => 'assignment'));

        foreach ($turnitinfiles as &$turnitinfile) {
            $coursemodule = $DB->get_record('course_modules', array('id' => $turnitinfile->cm));
            if (!$coursemodule) {
                $DB->delete_records('turnitin_files', array('id' => $turnitinfile->id));
                continue;
            }
            $modulecontext = get_context_instance(CONTEXT_MODULE, $coursemodule->id);
            if (!$modulecontext) {
                $DB->delete_records('turnitin_files', array('id' => $turnitinfile->id));
                continue;
            }
            if ($coursemodule->module == $assignmentid) {
                $submission = $DB->get_record('assignment_submissions',
                                              array('assignment' => $coursemodule->instance,
                                                    'userid' => $turnitinfile->userid));
                if (!$submission) {
                    $DB->delete_records('turnitin_files', array('id' => $turnitinfile->id));
                    continue;
                }
                $files = $fs->get_area_files($modulecontext->id, 'mod_assignment',
                                             'submission', $submission->id);
            } else if ($coursemodule->module == $courseworkid) {
                $submission = $DB->get_record('coursework_submissions',
                                              array('courseworkid' => $coursemodule->instance,
                                                    'userid' => $turnitinfile->userid));
                if (!$submission) {
                    $DB->delete_records('turnitin_files', array('id' => $turnitinfile->id));
                    continue;
                }
                $files = $fs->get_area_files($modulecontext->id, 'mod_coursework',
                                             'submission', $submission->id);
            }
            if ($files) {
                foreach ($files as $file) {
                    if ($file->get_contenthash() == $turnitinfile->identifier) {
                        $turnitinfile->identifier = $file->get_pathnamehash();
                        $DB->update_record('turnitin_files', $turnitinfile);
                        echo "Updated turnitin file id {$turnitinfile->id}. Old identifier: ".$file->get_contenthash().", new identifier ".$file->get_pathnamehash()."<br />";
                    }
                }
            }
        }
        upgrade_plugin_savepoint(true, 2011111000, 'plagiarism', 'turnitin');

    }

    if ($oldversion < 2011120500) {
        if (!$DB->record_exists('user_info_field', array('shortname'=>'turnitinteachercoursecache'))) {
            //first insert category
            $newcat = new stdClass();
            $newcat->name = 'plagiarism_turnitin';
            $newcat->sortorder = 999;
            $catid = $DB->insert_record('user_info_category', $newcat);
            //now insert field
            $newfield = new stdClass();
            $newfield->shortname = 'turnitinteachercoursecache';
            $newfield->name = get_string('userprofileteachercache', 'plagiarism_turnitin');
            $newfield->description = get_string('userprofileteachercache_desc', 'plagiarism_turnitin');
            $newfield->datatype = 'text';
            $newfield->descriptionformat = 1;
            $newfield->categoryid = $catid;
            $newfield->sortorder = 1;
            $newfield->required = 0;
            $newfield->locked = 1;
            $newfield->visible = 0;
            $newfield->forceunique = 0;
            $newfield->signup = 0;
            $newfield->param1 = 30;
            $newfield->param2 = 5000;

            $DB->insert_record('user_info_field', $newfield);
        }
    }
    if ($oldversion < 2011121200) {
        // All assignments need to have a start time

        $existingassignments = $DB->get_records('turnitin_config', array('name' => 'turnitin_assignid'));

        foreach ($existingassignments as $assignment) {
            $setting = new stdClass();
            $setting->cm = $assignment->cm;
            $setting->name = 'turnitin_dtstart';

            $coursemodule = $DB->get_record('course_modules', array('id' => $assignment->cm));
            $module = false;
            if ($coursemodule) {
                $modulename = $DB->get_field('modules', 'name', array('id' => $coursemodule->module));
                $module = $DB->get_record($modulename, array('id' => $coursemodule->instance));
            }
            if (!empty($module->timeavailable)) {
                $setting->value = $module->timeavailable;
            } else if (!empty($module->timecreated)) {
                $setting->value = $module->timecreated;
            } else {
                // Doesn't matter hugely that this is not the same as the actual turnitin start date. It just needs
                // to be ahead of the actual one and in the past relative to any future submitted files.
                $setting->value = time();
            }
            $DB->insert_record('turnitin_config', $setting);
        }

        upgrade_plugin_savepoint(true, 2011121200, 'plagiarism', 'turnitin');

    }
    if ($oldversion < 2011121201) {
        $table = new xmldb_table('turnitin_files');
        if ($dbman->table_exists($table)) {
            $dbman->rename_table($table, 'plagiarism_turnitin_files');
        }

        $table = new xmldb_table('turnitin_config');
        if ($dbman->table_exists($table)) {
            $dbman->rename_table($table, 'plagiarism_turnitin_config');
        }

        upgrade_plugin_savepoint(true, 2011121201, 'plagiarism', 'turnitin');
    }

    if ($oldversion < 2011121400) {

        // Define field legacyteacher to be added to plagiarism_turnitin_files
        $table = new xmldb_table('plagiarism_turnitin_files');
        $field = new xmldb_field('legacyteacher', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'apimd5');

        // Conditionally launch add field legacyteacher
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // turnitin savepoint reached
        upgrade_plugin_savepoint(true, 2011121400, 'plagiarism', 'turnitin');
    }

    if ($oldversion < 2012050901) {
        //get Turnitin lib
        require_once($CFG->dirroot.'/plagiarism/turnitin/lib.php');
        // Add config turnitin_assign to handle title renames
        $sql = "SELECT cm.id AS cmid, m.name AS modulename, cm.instance, cm.course
                FROM mdl_course_modules cm, mdl_modules m
                WHERE cm.id IN (SELECT tc.cm FROM mdl_plagiarism_turnitin_config tc
                                WHERE tc.name = 'turnitin_assignid'
                                )
                AND cm.module = m.id
                ORDER BY cm.id";
        $records = $DB->get_records_sql($sql);
        foreach ($records as $record) {
            $name = $DB->get_field($record->modulename, 'name', array('id'=>$record->instance));
            if ($name) {
                $assign = turnitin_get_assign_name($name, $record->cmid) ;
                $config = new stdClass();
                $config->cm = $record->cmid;
                $config->name = 'turnitin_assign';
                $config->value = $assign;
                if (!$DB->record_exists('plagiarism_turnitin_config', (array) $config)) {
                    $DB->insert_record('plagiarism_turnitin_config', $config);
                }
            }
        }
        // turnitin savepoint reached
        upgrade_plugin_savepoint(true, 2012050901, 'plagiarism', 'turnitin');
    }

    return true;
}
