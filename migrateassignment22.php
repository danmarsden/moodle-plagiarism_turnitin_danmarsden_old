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
 * migrateassignment22.php - migrates Assignment 2.2 settings to upgraded 2.3 assignments.
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
require_once('turnitin_form.php');

$fix19assignments = optional_param('fix19', 0, PARAM_INT);

require_login();
admin_externalpage_setup('plagiarismturnitin');
$PAGE->set_url('/plagiarism/turnitin/migrateassignment22.php');

echo $OUTPUT->header();
$currenttab='turnitinmigrate';
require_once('turnitin_tabs.php');

echo $OUTPUT->box(get_string('turnitinmigrate_help', 'plagiarism_turnitin'));

if ($fix19assignments) { //script to fix 19 legacy assignments that weren't migrated in 2.2
    $fs = get_file_storage();
    //now do tii_files table
    $tii_files = $DB->get_records('tii_files_legacy');
    if (!empty($tii_files)) {
        $i = 0;
        foreach ($tii_files as $tf) {
            //first check if this file already exists in plagiarism_turnitin_files
            if ($DB->record_exists('plagiarism_turnitin_files', array('externalid' => $tf->tii))) {
                echo "alreadyexists";
                continue;
            }
            $newf = new stdClass();
            $newf->userid = $tf->userid;
            $newf->externalid = $tf->tii;
            $newf->exernalstatus = 0;
            $newf->statuscode = $tf->tiicode;
            $newf->similarityscore = $tf->tiiscore;
            $newf->legacyteacher = 1;

            //now get oldcm based on course and module from log
            $oldcm = $DB->get_field('log', 'cmid', array('course' => $tf->course, 'action' => 'delete mod', 'info' => 'assignment '.$tf->instance));
            $newcm = $DB->get_field('log', 'cmid', array('course' => $tf->course, 'action' => 'view', 'module' => 'assign', 'info' => $oldcm));
            if (!empty($oldcm) && !empty($newcm)) {
                $newf->cm = $newcm;
                //now get the pathnamehash for this old file
                //first get all the files from the assignment module.
                $modulecontext = context_module::instance($newcm);
                //get instanceid of this assign
                $instanceid = $DB->get_field('course_modules', 'instance', array('id' => $newcm));
                $submission = $DB->get_record('assign_submission', array('assignment'=>$instanceid, 'userid'=>$tf->userid));
                $files = $fs->get_area_files($modulecontext->id, 'assignsubmission_file', 'submission_files', $submission->id, "id", false);
                foreach ($files as $file) {
                    if ($file->get_filename()==$tf->filename) {
                        $newf->identifier = $file->get_pathnamehash();
                    }
                }
                if (!empty($newf->identifier)) {
                    $i++;
                    $DB->insert_record('plagiarism_turnitin_files', $newf);
                }
            }
        }
        echo "migrated ".$i . "files";
    }
}

if ((data_submitted()) && confirm_sesskey()) {
    $sql = "SELECT DISTINCT cmid, info FROM {log} WHERE module='assign' AND action='view'";
    $rs = $DB->get_recordset_sql($sql);
    foreach ($rs as $r) {
        if (is_numeric($r->info) && $r->cmid <> $r->info) {
            $oldcm = (int) $r->info;
            //update config with new cm

            if (!$DB->record_exists('course_modules', array('id' => $oldcm)) && //check that the $oldcm doesn't exist - if it still exists we shouldn't do anything here.
                 $DB->record_exists('course_modules', array('id' => $r->cmid)) && //check that the new cm still exists.
                 $DB->record_exists('plagiarism_turnitin_config', array('cm'=>$oldcm))) { //check that we have records to update.

                //update turnitin config with new cm
                $DB->set_field('plagiarism_turnitin_config', 'cm', $r->cmid, array('cm' => $oldcm));

                //update turnitin files with new cm
                $DB->set_field('plagiarism_turnitin_files', 'cm', $r->cmid, array('cm' => $oldcm));

            }
        }
    }
    $rs->close();
    echo $OUTPUT->notification(get_string('migrated', 'plagiarism_turnitin'), 'notifysuccess');
} else {
    echo $OUTPUT->single_button($PAGE->url, get_string('turnitinmigrate', 'plagiarism_turnitin'));
}

echo $OUTPUT->footer();
