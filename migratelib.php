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
 * migrateassignment22.php - cli script to fix assignment cmids.
 *
 * @package   plagiarism_turnitin
 * @author    Dan Marsden <dan@danmarsden.com>
 * @copyright 2012 Dan Marsden
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

//DODGY Hackery HERE!!!!! - uses fragile log entries to map old cmid to new one.
//USE AT OWN RISK!

//this function converts the oldcm id to the new one in the config/files tables as needed.
//migration of files from 1.9 versions will only work if the migration scripts were run in 2.0->2.2
//if you forget to do this you can see the other function in this file.

function turnitin_migratecmids() {
    global $DB;
    //first get instances of deleted mods
    $sql = "SELECT DISTINCT cmid, info FROM {log} WHERE module='course' AND action='delete mod' AND info LIKE 'assignment%'";
    $rs = $DB->get_recordset_sql($sql);
    foreach ($rs as $r) {
        $instance = str_replace('assignment ', '', $r->info);
        if (!is_numeric($instance)) {
            mtrace("not valid instance:".$r->info);
            continue;
        }
        //now get newcm based on instance.
        //this is an old log entry that has the cmid translated but the old instanceid is kept in the info field.
        $newcm = $DB->get_field('log', 'cmid', array('action' => 'view', 'module' => 'assign', 'info' => $instance));

        if (!$DB->record_exists('course_modules', array('id' => $r->cmid)) && //sanity check that the $r->cmid doesn't exist - if it still exists we shouldn't do anything here.
            $DB->record_exists('course_modules', array('id' => $newcm)) && //check that the new cm still exists.
            $DB->record_exists('plagiarism_turnitin_config', array('cm'=>$r->cmid))) { //check that we have records to update.

            //update turnitin config with new cm
            $DB->set_field('plagiarism_turnitin_config', 'cm', $newcm, array('cm' => $r->cmid));

            //update turnitin files with new cm
            $DB->set_field('plagiarism_turnitin_files', 'cm', $newcm, array('cm' => $r->cmid));
        }
    }
    $rs->close();
}



// this function is used when a site is upgraded from 1.9->2.3 and the turnitin files migration was skipped in 2.0-2.2
// the code in 2.0->2.2 if installed and migration scripts are run will negate the need for this whacky function.
// usually the old instance/course key as used in 1.9 will have been converted in a 2.0->2.2 install - but if a site goes basically straight from 1.9->2.3 you need this.
function turnitin_fix19assignments() {
    global $DB;
    $fs = get_file_storage();
    //now do tii_files table
    $tii_files = $DB->get_records('tii_files_legacy');
    if (!empty($tii_files)) {
        $i = 0;
        $exists = 0;
        $ignore = 0;
        foreach ($tii_files as $tf) {
            //first check if this file already exists in plagiarism_turnitin_files
            if (empty($tf->tii)) {
                $ignore++;
                continue; //only migrate files that have a valid report.
            }
            if ($DB->record_exists('plagiarism_turnitin_files', array('externalid' => $tf->tii))) {
                echo "alreadyexists - migrated previously:".$tf->tii."<br>";
                $exists++;
                continue;
            }
            $newf = new stdClass();
            $newf->userid = $tf->userid;
            $newf->externalid = $tf->tii;
            $newf->exernalstatus = 0;
            $newf->statuscode = $tf->tiicode;
            $newf->similarityscore = $tf->tiiscore;
            $newf->legacyteacher = 1;


            //$oldcm = $DB->get_field('log', 'cmid', array('course' => $tf->course, 'action' => 'delete mod', 'info' => 'assignment '.$tf->instance));
            $newcm = $DB->get_field('log', 'cmid', array('course' => $tf->course, 'action' => 'view', 'module' => 'assign', 'info' => $tf->instance));
            if (!empty($newcm)) {
                $newf->cm = $newcm;
                //now get the pathnamehash for this old file
                //first get all the files from the assignment module.
                $modulecontext = context_module::instance($newcm);
                //get instanceid of this assign
                $instanceid = $DB->get_field('course_modules', 'instance', array('id' => $newcm));
                if (empty($instanceid)) {
                    mtrace("couldn't find instanceid cm: ".$newcm);
                    continue;
                }
                $submission = $DB->get_record('assign_submission', array('assignment'=>$instanceid, 'userid'=>$tf->userid));
                if (empty($submission)) {
                    mtrace("couldn't find submission: ".$instanceid. 'user:'.$tf->userid);
                    continue;
                }

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
            } else {
                mtrace("could not find oldcm instance:".$tf->instance);
            }
        }
        echo "migrated ".$i . "files";
        echo $exists . " already exist";
        echo 'ignored' .$ignore . " files as they didn't have a valid report.";
    }
}