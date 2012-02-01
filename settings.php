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
 * plagiarism.php - allows the admin to configure plagiarism stuff
 *
 * @package   plagiarism_turnitin
 * @author    Dan Marsden <dan@danmarsden.com>
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(__FILE__)) . '/../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/plagiarismlib.php');
require_once($CFG->dirroot.'/plagiarism/turnitin/lib.php');

require_login();
admin_externalpage_setup('plagiarismturnitin');

$context = get_context_instance(CONTEXT_SYSTEM);

require_capability('moodle/site:config', $context, $USER->id, true, "nopermissions");

require_once('turnitin_form.php');
$mform = new turnitin_setup_form();
$plagiarismplugin = new plagiarism_plugin_turnitin();
$plagiarismsettings = $plagiarismplugin->get_settings();

if ($mform->is_cancelled()) {
    redirect('');
}

echo $OUTPUT->header();
$currenttab='turnitinsettings';
require_once('turnitin_tabs.php');

if (($data = $mform->get_data()) && confirm_sesskey()) {
    if (!isset($data->turnitin_use)) {
        $data->turnitin_use = 0;
    }
    if (!isset($data->turnitin_enablegrademark)) {
        $data->turnitin_enablegrademark = 0;
    }
    if (!isset($data->turnitin_senduseremail)) {
        $data->turnitin_senduseremail = 0;
    }
    foreach ($data as $field => $value) {
        if (strpos($field, 'turnitin')===0) {
            if ($tiiconfigfield = $DB->get_record('config_plugins', array('name'=>$field, 'plugin'=>'plagiarism'))) {
                $tiiconfigfield->value = $value;
                if (! $DB->update_record('config_plugins', $tiiconfigfield)) {
                    error("errorupdating");
                }
            } else {
                $tiiconfigfield = new stdClass();
                $tiiconfigfield->value = $value;
                $tiiconfigfield->plugin = 'plagiarism';
                $tiiconfigfield->name = $field;
                if (! $DB->insert_record('config_plugins', $tiiconfigfield)) {
                    error("errorinserting");
                }
            }
        }
    }
    $plagiarismsettings = $plagiarismplugin->get_settings();
    //now call TII settings to set up teacher account as set on this page.
    if ($plagiarismsettings) { //get tii settings.
        $tii = array();
        $tii['utp']   = TURNITIN_INSTRUCTOR;
        $tii['fcmd']  = TURNITIN_RETURN_XML;
        $tii['fid']   = TURNITIN_CREATE_USER;
        $tii = turnitin_get_tii_user($tii, $USER);
        $tiixml = plagiarism_get_xml(turnitin_get_url($tii, $plagiarismsettings));
        if (!empty($tiixml->rcode[0]) && $tiixml->rcode[0] == '11') {
            notify(get_string('savedconfigsuccess', 'plagiarism_turnitin'), 'notifysuccess');
        } else {
            //disable turnitin as this config isn't correct.
            $rec =  $DB->get_record('config_plugins', array('name'=>'turnitin_use', 'plugin'=>'plagiarism'));
            $rec->value = 0;
            $DB->update_record('config_plugins', $rec);
            notify(get_string('savedconfigfailure', 'plagiarism_turnitin'));
        }
    }
}

$mform->set_data($plagiarismsettings);

//check for old 1.9 tables and display upgrade button.
$dbman = $DB->get_manager();
$table = new xmldb_table('tii_files');
$table2 = new xmldb_table('plagiarism_config');
if ($dbman->table_exists($table) && $dbman->table_exists($table2)) {
    /// increase script runtime
    set_time_limit(0);
    /// increase memory limit
    raise_memory_limit(MEMORY_EXTRA);
    // do plagiarism_config table - easy as structure stays the same
    $plagiarism_config = $DB->get_records('plagiarism_config');
    if (!empty($plagiarism_config)) {
        foreach ($plagiarism_config as $pg) {
            $newpg = new stdClass();
            $newpg->cm = $pg->cm;
            $newpg->name = $pg->name;
            $newpg->value = $pg->value;
            $DB->insert_record('plagiarism_turnitin_config', $newpg);
        }
    }
    $dbman->rename_table($table2, 'tii_config_legacy');
    // get filesystem
    $fs = get_file_storage();
    //now do tii_files table
    $tii_files = $DB->get_records('tii_files');
    if (!empty($tii_files)) {
        foreach ($tii_files as $tf) {
            $newf = new stdClass();
            $newf->userid = $tf->userid;
            $newf->externalid = $tf->tii;
            $newf->exernalstatus = 0;
            $newf->statuscode = $tf->tiicode;
            $newf->similarityscore = $tf->tiiscore;
            $newf->legacyteacher = 1;

            //now get cm based on course and module
            $cm = $DB->get_record('course_modules', array('course'=>$tf->course,
                                                          'module'=>$tf->module,
                                                          'instance'=>$tf->instance));
            if (!empty($cm)) {
                $newf->cm = $cm->id;
                //now get the pathnamehash for this old file
                //first get all the files from the assignment module.
                $modulecontext = get_context_instance(CONTEXT_MODULE, $cm->id);
                $submission = $DB->get_record('assignment_submissions', array('assignment'=>$tf->instance, 'userid'=>$tf->userid));
                $files = $fs->get_area_files($modulecontext->id, 'mod_assignment', 'submission', $submission->id);
                foreach ($files as $file) {
                    if ($file->get_filename()==$tf->filename) {
                        $newf->identifier = $file->get_pathnamehash();
                    }
                }
                if (!empty($newf->identifier)) {
                    $DB->insert_record('plagiarism_turnitin_files', $newf);
                }
            }
        }
    }
    $dbman->rename_table($table, 'tii_files_legacy');
}
//finished check for old tables..


if ($plagiarismsettings) {
    //Now show link to ADMIN tii interface - NOTE: this logs in the ADMIN user, should be hidden from normal teachers.
    $tii['utp'] = TURNITIN_ADMIN;
    $tii = turnitin_get_tii_user($tii, $USER);
    $tii['fcmd'] = TURNITIN_LOGIN; //when set to 2 this returns XML
    $tii['fid'] = TURNITIN_ADMIN_STATS; //set commands - Administrator login/statistics.
    echo '<div align="center">';
    echo '<a href="'.turnitin_get_url($tii, $plagiarismsettings).'" target="_blank">'.get_string("adminlogin", "plagiarism_turnitin").'</a><br/>';
    $tii['utp'] = TURNITIN_INSTRUCTOR;
    $tii['fid'] = TURNITIN_CREATE_USER; //set commands - Administrator login/statistics.
    echo '<a href="'.turnitin_get_url($tii, $plagiarismsettings).'" target="_blank">'.get_string("teacherlogin", "plagiarism_turnitin").'</a>';
    echo '</div>';
}

echo $OUTPUT->box_start('generalbox boxaligncenter', 'intro');
$mform->display();
echo $OUTPUT->box_end();
echo $OUTPUT->footer();