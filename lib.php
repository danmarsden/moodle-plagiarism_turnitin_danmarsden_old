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
 * lib.php - Contains Turnitin soecific functions called by Modules.
 *
 * @since 2.0
 * @package    plagiarism_turnitin
 * @subpackage plagiarism
 * @copyright  2010 Dan Marsden http://danmarsden.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}
define('PLAGIARISM_TII_SHOW_NEVER', 0);
define('PLAGIARISM_TII_SHOW_ALWAYS', 1);
define('PLAGIARISM_TII_SHOW_CLOSED', 2);


//Turnitin fcmd types - return values.
define('TURNITIN_LOGIN', 1);
define('TURNITIN_RETURN_XML', 2);
define('TURNITIN_UPDATE_RETURN_XML', 3);
// Turnitin user types
define('TURNITIN_STUDENT', 1);
define('TURNITIN_INSTRUCTOR', 2);
define('TURNITIN_ADMIN', 3);
//Turnitin API actions.
define('TURNITIN_CREATE_USER', 1);
define('TURNITIN_CREATE_CLASS', 2);
define('TURNITIN_JOIN_CLASS', 3);
define('TURNITIN_CREATE_ASSIGNMENT', 4);
define('TURNITIN_SUBMIT_PAPER', 5);
define('TURNITIN_RETURN_REPORT', 6);
define('TURNITIN_VIEW_SUBMISSION', 7);
define('TURNITIN_DELETE_SUBMISSION', 8); //unlikely to need this.
define('TURNITIN_LIST_SUBMISSIONS', 10);
define('TURNITIN_CHECK_SUBMISSION', 11);
define('TURNITIN_ADMIN_STATS', 12);
define('TURNITIN_RETURN_GRADEMARK', 13);
define('TURNITIN_REPORT_TIME', 14);
define('TURNITIN_SUBMISSION_SCORE', 15);
define('TURNITIN_START_SESSION', 17);
define('TURNITIN_END_SESSION', 18);
//Turnitin allowed file types
define('TURNITIN_TYPE_TEXT', 1);
define('TURNITIN_TYPE_FILE', 2);

//Turnitin Response codes - there are many more of these, just not used directly.
//
define('TURNITIN_RESP_USER_CREATED', 11); // User creation successful, do not send to login
define('TURNITIN_RESP_CLASS_CREATED_LOGIN', 20); // Class created successfully, send to login
define('TURNITIN_RESP_CLASS_CREATED', 21); // Class Created successfully, do not send to login
define('TURNITIN_RESP_CLASS_UPDATED', 22); // Class updated successfully
define('TURNITIN_RESP_USER_JOINED', 31); // successful, User joined to class, do not sent to login
define('TURNITIN_RESP_ASSIGN_CREATED', 41); // Assignment Created
define('TURNITIN_RESP_ASSIGN_MODIFIED', 42); // Assignment modified
define('TURNITIN_RESP_ASSIGN_DELETED', 43); // Assignment deleted
define('TURNITIN_RESP_PAPER_SENT', 51); // paper submitted
define('TURNITIN_RESP_SCORE_RECEIVED', 61); // Originality score retrieved.
define('TURNITIN_RESP_ASSIGN_EXISTS', 419); // Assignment already exists.
define('TURNITIN_RESP_SCORE_NOT_READY', 415);

//get global class
global $CFG;
require_once($CFG->dirroot.'/plagiarism/lib.php');

///// Turnitin Class ////////////////////////////////////////////////////
class plagiarism_plugin_turnitin extends plagiarism_plugin {
    public function get_links($linkarray) {
        global $DB, $USER, $COURSE;
        $cmid = $linkarray['cmid'];
        $userid = $linkarray['userid'];
        //$userid, $file, $cmid, $course, $module
        //TODO: the following check is hardcoded to the Assignment module - needs updating to be generic.
        if (isset($linkarray['assignment'])) {
            $module = $linkarray['assignment'];
        } else {
            $modulesql = 'SELECT m.id, m.name, cm.instance'.
                    ' FROM {course_modules} cm' .
                    ' INNER JOIN {modules} m on cm.module = m.id ' .
                    'WHERE cm.id = ?';
            $moduledetail = $DB->get_record_sql($modulesql, array($cmid));
            if (!empty($moduledetail)) {
                $sql = "SELECT * FROM {?} WHERE id= ?";
                $module = $DB->get_record_sql($sql, array($moduledetail->name, $moduledetail->instance));
            }
        }
        if (empty($module)) {
            return '';
        }

        $plagiarismvalues = $DB->get_records_menu('turnitin_config', array('cm'=>$cmid),'','name,value');
        if (empty($plagiarismvalues['use_turnitin'])) {
            //nothing to do here... move along!
           return '';
        }
        $modulecontext = get_context_instance(CONTEXT_MODULE, $cmid);

        if ($USER->id == $userid) {
            // The user wants to see details on their own report
            $selfreport = true;
        } else {
            $selfreport = false;
        }
        // Whether the user has permissions to see all items in the context of this module.
        $viewsimilarityscore = has_capability('plagiarism/turnitin:viewsimilarityscore', $modulecontext);
        $viewfullreport = has_capability('plagiarism/turnitin:viewfullreport', $modulecontext);

        if ($selfreport) {
            if ($plagiarismvalues['plagiarism_show_student_score'] == PLAGIARISM_TII_SHOW_ALWAYS) {
                $viewsimilarityscore = true;
            }
            if ($plagiarismvalues['plagiarism_show_student_report'] == PLAGIARISM_TII_SHOW_ALWAYS) {
                $viewfullreport = true;
            }
        }
        if (!$viewsimilarityscore && !$viewfullreport) {
            // The user has no right to see the requested detail.
            return '<br />';
        }

        $plagiarismsettings = $this->get_settings();
        if (empty($plagiarismsettings)) {
            // Turnitin is not enabled
            return '<br />';
        }
        $plagiarismfile = $DB->get_record_sql(
                "SELECT * FROM {turnitin_files}
                 WHERE cm = ? AND userid = ? AND " .
                $DB->sql_compare_text('identifier') . " = ?",
                array($cmid, $userid,$linkarray['file']->get_contenthash()));
        if (empty($plagiarismfile)) {
            // No record of that submission - so no links can be returned
            return '<br />';
        }

        if(isset($plagiarismfile->statuscode) && $plagiarismfile->statuscode !== 'success') {
            //always display errors - even if the student isn't able to see report/score.
            $output = turnitin_error_text($plagiarismfile->statuscode);
            return $output . "<br />";
        }

        // TII has successfully returned a score.
        $rank = plagiarism_get_css_rank($plagiarismfile->similarityscore);

        $similaritystring = '<span class="' . $rank . '">' . $plagiarismfile->similarityscore . '%</span>';
        if ($viewfullreport) {
            // User gets to see link to similarity report & similarity score
            $output = '<span class="plagiarismreport"><a href="'.turnitin_get_report_link($plagiarismfile, $COURSE, $plagiarismsettings).'" target="_blank">';
            $output .= get_string('similarity', 'plagiarism_turnitin').':</a>' . $similaritystring . '</span>';
        } elseif ($viewsimilarityscore) {
            // User only sees similarity score
            $output = '<span class="plagiarismreport">' . get_string('similarity', 'plagiarism_turnitin') . $similaritystring . '</span>';
        } else {
            $output = '';
        }

        //now check if grademark enabled and return the status of this file.
        if (!empty($plagiarismsettings['turnitin_enablegrademark'])) {
                $output .= '<span class="grademark">'.turnitin_get_grademark_link($plagiarismfile, $COURSE, $module, $plagiarismsettings)."</span>";
        }
        return $output.'<br/>';
    }

    public function save_form_elements($data) {
            global $DB;
        if (!$this->get_settings()) {
            return;
        }
        if (isset($data->use_turnitin)) {
            //array of posible plagiarism config options.
            $plagiarismelements = $this->config_options();
            //first get existing values
            $existingelements = $DB->get_records_menu('turnitin_config', array('cm'=>$data->coursemodule),'','name,id');
            foreach($plagiarismelements as $element) {
                $newelement = new object();
                $newelement->cm = $data->coursemodule;
                $newelement->name = $element;
                $newelement->value = (isset($data->$element) ? $data->$element : 0);
                if (isset($existingelements[$element])) { //update
                    $newelement->id = $existingelements[$element];
                    $DB->update_record('turnitin_config', $newelement);
                } else { //insert
                    $DB->insert_record('turnitin_config', $newelement);
                }

            }
        }
    }

    public function get_form_elements_module($mform, $context) {
        global $CFG, $DB;
        if (!$this->get_settings()) {
            return;
        }
        $cmid = optional_param('update', 0, PARAM_INT); //there doesn't seem to be a way to obtain the current cm a better way - $this->_cm is not available here.
        if (!empty($cmid)) {
            $plagiarismvalues = $DB->get_records_menu('turnitin_config', array('cm'=>$cmid),'','name,value');
        }
        $plagiarismdefaults = $DB->get_records_menu('turnitin_config', array('cm'=>0),'','name,value'); //cmid(0) is the default list.
        $plagiarismelements = $this->config_options();
        if (has_capability('plagiarism/turnitin:enable', $context)) {
            turnitin_get_form_elements($mform);
            if ($mform->elementExists('plagiarism_draft_submit')) {
                $mform->disabledIf('plagiarism_draft_submit', 'var4', 'eq', 0);
            }
            //disable all plagiarism elements if use_plagiarism eg 0
            foreach ($plagiarismelements as $element) {
                if ($element <> 'use_turnitin') { //ignore this var
                    $mform->disabledIf($element, 'use_turnitin', 'eq', 0);
                }
            }
            //check if files have already been submitted and disable exclude biblio and quoted if turnitin is enabled.
            if ($DB->record_exists('turnitin_files', array('cm'=> $cmid))) {
                $mform->disabledIf('plagiarism_exclude_biblio','use_turnitin');
                $mform->disabledIf('plagiarism_exclude_quoted','use_turnitin');
            }
        } else { //add plagiarism settings as hidden vars.
            foreach ($plagiarismelements as $element) {
                $mform->addElement('hidden', $element);
            }
        }
        //now set defaults.
        foreach ($plagiarismelements as $element) {
            if (isset($plagiarismvalues[$element])) {
                $mform->setDefault($element, $plagiarismvalues[$element]);
            } else if (isset($plagiarismdefaults[$element])) {
                $mform->setDefault($element, $plagiarismdefaults[$element]);
            }
        }
    }

    public function print_disclosure($cmid) {
        global $DB, $OUTPUT;
        if ($plagiarismsettings = $this->get_settings()) {
            if (!empty($plagiarismsettings['turnitin_student_disclosure'])) {

                $sql = 'SELECT value
                        FROM {turnitin_config}
                        WHERE cm = ?
                        AND ' . $DB->sql_compare_text('name', 255) . ' = ' . $DB->sql_compare_text('?', 255);
                $params = array($cmid, 'use_turnitin');
                $showdisclosure = $DB->get_field_sql($sql, $params);
                if ($showdisclosure) {
                    echo $OUTPUT->box_start('generalbox boxaligncenter', 'intro');
                    $formatoptions = new stdClass;
                    $formatoptions->noclean = true;
                    echo format_text($plagiarismsettings['turnitin_student_disclosure'], FORMAT_MOODLE, $formatoptions);
                    echo $OUTPUT->box_end();
                }
            }
        }
    }
    /**
     * This function should be used to initialise settings and check if plagiarism is enabled
     * *
     * @return mixed - false if not enabled, or returns an array of relavant settings.
     */
    public function get_settings() {
        global $DB;
        $plagiarismsettings = (array)get_config('plagiarism');
        //check if tii enabled.
        if (isset($plagiarismsettings['turnitin_use']) && $plagiarismsettings['turnitin_use'] && isset($plagiarismsettings['turnitin_accountid']) && $plagiarismsettings['turnitin_accountid']) {
            //now check to make sure required settings are set!
            if (empty($plagiarismsettings['turnitin_secretkey'])) {
                print_error('missingkey', 'plagiarism_turnitin');
            }
            return $plagiarismsettings;
        } else {
            return false;
        }
    }
    public function config_options() {
        return array('use_turnitin','plagiarism_show_student_score','plagiarism_show_student_report',
                     'plagiarism_draft_submit','plagiarism_compare_student_papers','plagiarism_compare_internet',
                     'plagiarism_compare_journals','plagiarism_compare_institution','plagiarism_report_gen',
                     'plagiarism_exclude_biblio','plagiarism_exclude_quoted','plagiarism_exclude_matches',
                     'plagiarism_exclude_matches_value','plagiarism_anonymity');
    }

    public function update_status($course, $cm) {
        global $DB, $USER, $OUTPUT;
        $userprofilefieldname = 'turnitinteachercoursecache';
        if (!$plagiarismsettings = $this->get_settings()) {
            return;
        }

        //first check coursecache user info field to see if this user is already a member of the Turnitin course.
        if (empty($USER->profile[$userprofilefieldname]) || !in_array($course->id, explode(',',$USER->profile[$userprofilefieldname]))) {
            $existingcourses = explode(',',$USER->profile[$userprofilefieldname]);
            $userprofilefieldid = $DB->get_field('user_info_field', 'id', array('shortname'=>$userprofilefieldname));
            $tii = array();
            $tii['utp']      = TURNITIN_INSTRUCTOR;
            $tii = turnitin_get_tii_user($tii, $USER);
            $tii['cid']      = get_config('plagiarism_turnitin_course', $course->id); //course ID
            $tii['ctl']      = (strlen($course->shortname) > 45 ? substr($course->shortname, 0, 45) : $course->shortname);
            $tii['ctl']      = (strlen($tii['ctl']) > 5 ? $tii['ctl'] : $tii['ctl']."_____");
            $tii['fcmd'] = TURNITIN_RETURN_XML;
            $tii['fid']  = TURNITIN_CREATE_CLASS;
            $tiixml = plagiarism_get_xml(turnitin_get_url($tii, $plagiarismsettings));
            if ($tiixml->rcode[0] != TURNITIN_RESP_CLASS_CREATED) {
                $OUTPUT->notification(get_string('errorassigninguser','plagiarism_turnitin'));
                return;
            }
            $existingcourses[] = $course->id;
            $newcoursecache =  implode(',',$existingcourses);
            $DB->set_field('user_info_data','data', $newcoursecache,array('userid'=>$USER->id, 'fieldid'=>$userprofilefieldid));
            $USER->profile[$userprofilefieldname] = $newcoursecache;
        }

        $tii = array();
        //print link to teacher login
        $tii['fcmd'] = TURNITIN_LOGIN; //when set to 2 this returns XML
        $tii['utp'] = TURNITIN_INSTRUCTOR;
        $tii['fid'] = TURNITIN_CREATE_USER; //set commands - Administrator login/statistics.
        $tii = turnitin_get_tii_user($tii, $USER);
        echo '<div style="text-align:right"><a href="'.turnitin_get_url($tii, $plagiarismsettings).'" target="_blank">'.get_string("teacherlogin","plagiarism_turnitin").'</a></div>';

        //currently only used for grademark - check if enabled and return if not.
        //TODO: This call degrades page performance - need to run less frequently.
        if (empty($plagiarismsettings['turnitin_enablegrademark'])) {
            return;
        }
        if (!$moduletype = $DB->get_field('modules','name', array('id'=>$cm->module))) {
            debugging("invalid moduleid! - moduleid:".$cm->module);
        }
        if (!$module = $DB->get_record($moduletype, array('id'=>$cm->instance))) {
            debugging("invalid instanceid! - instance:".$cm->instance." Module:".$moduletype);
        }

        //set globals.
        $tii['utp']      = TURNITIN_INSTRUCTOR;
        $tii = turnitin_get_tii_user($tii, $USER);
        $tii['cid']      = get_config('plagiarism_turnitin_course', $course->id); //course ID
        $tii['ctl']      = (strlen($course->shortname) > 45 ? substr($course->shortname, 0, 45) : $course->shortname);
        $tii['ctl']      = (strlen($tii['ctl']) > 5 ? $tii['ctl'] : $tii['ctl']."_____");
        $turnitin_assignid = $DB->get_field('turnitin_config','value', array('cm'=>$cm->id, 'name'=>'turnitin_assignid'));
        if (!empty($turnitin_assignid)) {
            $tii['assignid'] = $turnitin_assignid;
        }
        $tii['assign']   = (strlen($module->name) > 90 ? substr($module->name, 0, 90) : $module->name); //assignment name stored in TII
        $tii['fcmd']     = TURNITIN_RETURN_XML;
        $tii['fid']      = TURNITIN_LIST_SUBMISSIONS;
        $tiixml = plagiarism_get_xml(turnitin_get_url($tii, $plagiarismsettings));

        if (!empty($tiixml->object)) {
            //get full list of turnitin_files for this cm
            $grademarkstatus= array();
            foreach($tiixml->object as $tiiobject) {
                $grademarkstatus[(int)$tiiobject->objectID[0]] = (int)$tiiobject->gradeMarkStatus[0];
            }
            if (!empty($grademarkstatus)) {
                $plagiarsim_files = $DB->get_records('turnitin_files', array('cm'=>$cm->id));
                foreach ($plagiarsim_files as $file) {
                    if (isset($grademarkstatus[$file->externalid]) && $file->externalstatus <> $grademarkstatus[$file->externalid]) {
                        $file->externalstatus = $grademarkstatus[$file->externalid];
                        $DB->update_record('turnitin_files', $file);
                    }
                }
            }
        }
    }
    /**
     * used by admin/cron.php to get similarity scores from submitted files.
     *
     */
    public function cron() {
        global $CFG, $DB;
        require_once("$CFG->libdir/filelib.php"); //HACK to include filelib so that when event cron is run then file_storage class is available
        require_once("$CFG->dirroot/mod/assignment/lib.php"); //HACK to include filelib so that when event cron is run then file_storage class is available
        $plagiarismsettings = $this->get_settings();
        if ($plagiarismsettings) {
            turnitin_get_scores($plagiarismsettings);
        }
        //get list of files that need to be resubmitted - sanity check against cm
        if (!empty($plagiarismsettings['turnitin_attempts']) && is_numeric($plagiarismsettings['turnitin_attempts'])
            && !empty($plagiarismsettings['turnitin_attemptcodes'])) {

            $attemptcodes = explode(',',trim($plagiarismsettings['turnitin_attemptcodes']));
            list($usql, $params) = $DB->get_in_or_equal($attemptcodes);
            $sql = "SELECT tf.*
                    FROM {turnitin_files} tf, {course_modules} cm
                    WHERE tf.cm = cm.id AND
                    tf.statuscode $usql AND tf.attempt < ".$plagiarismsettings['turnitin_attempts'];
            $items = $DB->get_records_sql($sql, $params);
            foreach ($items as $item) {
                    $foundfile = false;
                    //TODO: hardcoded to assignment here - need to check for correct file location.
                    $assignmentbase = new assignment_base($item->cm);
                    $modulecontext = get_context_instance(CONTEXT_MODULE, $item->cm);
                    $fs = get_file_storage();
                    //TODO: check to see if there's a more efficient way to select just one file based on the id instead of iterating through all files to find it.
                    $submission = $assignmentbase->get_submission($item->userid);
                    if ($files = $fs->get_area_files($modulecontext->id, 'mod_assignment', 'submission',$submission->id)) {
                        foreach ($files as $file) {
                            if ($file->get_filename()==='.') {
                                continue;
                            }
                            if ($file->get_contenthash() == $item->identifier) {
                                $foundfile = true;
                                $pid = plagiarism_update_record($item->cm, $item->userid, $file->get_contenthash(), $item->attempt+1);
                                if (!empty($pid)) {
                                    turnitin_send_file($pid, $plagiarismsettings, $file);
                                }
                            }
                        }
                    }
                    if (!$foundfile) {
                        debugging('file resubmit attempted but file not found id:'.$item->id, DEBUG_DEVELOPER);
                    }
            }
        }
    }
    public function event_handler($eventdata) {
        global $DB, $CFG;
        $result = true;
        $plagiarismsettings = $this->get_settings();
        $cmid = (!empty($eventdata->cm->id)) ? $eventdata->cm->id : $eventdata->cmid;
        $plagiarismvalues = $DB->get_records_menu('turnitin_config', array('cm'=>$cmid),'','name,value');
        if (!$plagiarismsettings || empty($plagiarismvalues['use_turnitin'])) {
            //nothing to do here... move along!
            return $result;
        }

        if ($eventdata->eventtype == "mod_created") {
            return turnitin_update_assignment($plagiarismsettings, $plagiarismvalues, $eventdata, 'create');
        } else if ($eventdata->eventtype=="mod_updated") {
            return  turnitin_update_assignment($plagiarismsettings, $plagiarismvalues, $eventdata, 'update');
        } else if ($eventdata->eventtype=="mod_deleted") {
           return  turnitin_update_assignment($plagiarismsettings, $plagiarismvalues, $eventdata, 'delete');
        } else if ($eventdata->eventtype=="file_uploaded") {
            // check if the module associated with this event still exists
            if (!$DB->record_exists('course_modules', array('id' => $eventdata->cmid))) {
                return $result;
            }

            if (!empty($eventdata->file) && empty($eventdata->files)) { //single assignment type passes a single file
                $eventdata->files[] = $eventdata->file;
            }

            if (!empty($eventdata->files)) { //this is an upload event with multiple files
                foreach ($eventdata->files as $efile) {
                    if ($efile->get_filename() ==='.') {
                        continue;
                    }
                    //hacky way to check file still exists
                    $fs = get_file_storage();
                    $fileid = $fs->get_file_by_hash($efile->get_pathnamehash());
                    if (empty($fileid)) {
                        mtrace("nofilefound!");
                        continue;
                    }
                    if (empty($plagiarismvalues['plagiarism_draft_submit'])) { //check if this is an advanced assignment and shouldn't send the file yet.
                        //TODO - check if this particular file has already been submitted.
                        $pid = plagiarism_update_record($cmid, $eventdata->userid, $efile->get_contenthash());
                        if (!empty($pid)) {
                            $result = turnitin_send_file($pid, $plagiarismsettings, $efile);
                        }
                    }
                }
            } else { //this is a finalize event
                mtrace("finalise");
                if (isset($plagiarismvalues['plagiarism_draft_submit']) && $plagiarismvalues['plagiarism_draft_submit'] == 1) { // is file to be sent on final submission?
                    require_once("$CFG->dirroot/mod/assignment/lib.php"); //HACK to include filelib so that when event cron is run then file_storage class is available
                    // we need to get a list of files attached to this assignment and put them in an array, so that
                    // we can submit each of them for processing.
                    $assignmentbase = new assignment_base($cmid);
                    $submission = $assignmentbase->get_submission($eventdata->userid);
                    $modulecontext = get_context_instance(CONTEXT_MODULE, $eventdata->cmid);
                    $fs = get_file_storage();
                    if ($files = $fs->get_area_files($modulecontext->id, 'mod_assignment', 'submission', $submission->id, "timemodified")) {
                        foreach ($files as $file) {
                            if ($file->get_filename()==='.') {
                                continue;
                            }
                            //TODO: need to check if this file has already been sent! - possible that the file was sent before draft submit was set.
                            $pid = plagiarism_update_record($cmid, $eventdata->userid, $file->get_contenthash());
                            if (!empty($pid)) {
                                $result = turnitin_send_file($pid, $plagiarismsettings, $file);
                            }
                        }
                    }
                }
            }
            return $result;
        } else if ($eventdata->eventtype=="quizattempt") {
            //get list of essay questions and the users answer in this quiz
            $sql = "SELECT s.* FROM {question} q, {quiz_question_instances} i, {question_states} s, {question_sessions} qs
                    WHERE i.quiz = ? AND i.quiz=q.id AND q.qtype='essay'
                    AND s.question = q.id AND qs.questionid= q.id AND qs.newest = s.id AND qs.attemptid = s.attempt AND s.attempt = ?";
            $essayquestions = $DB->get_records_sql($sql, array($eventdata->quiz, $eventdata->attempt));
            //check dir exists
            if (!file_exists($CFG->dataroot."/temp/turnitin")) {
                if (!file_exists($CFG->dataroot."/temp")) {
                    mkdir($CFG->dataroot."/temp",0700);
                }
                mkdir($CFG->dataroot."/temp/turnitin",0700);
            }
            foreach($essayquestions as $qid) {
                //get actual response
                //create file to send
                $pid = plagiarism_update_record($cmid, $eventdata->userid, $qid->id);
                if (!empty($pid)) {
                    $file = new stdclass();
                    $file->type = "tempturnitin";
                    $file->filename = $pid .".txt";
                    $file->timestamp = $qid->timestamp;
                    $file->filepath =  $CFG->dataroot."/temp/turnitin/" . $pid .".txt";
                    $fd = fopen($file->filepath,'wb');   //create if not exist, write binary
                    fwrite( $fd, $qid->answer);
                    fclose( $fd );
                    $result = turnitin_send_file($pid, $plagiarismsettings, $file);
                    unlink($file->filepath); //delete temp file.
                }
            }
            return true;
        } else {
            //return true; //Don't need to handle this event
        }
    }
}

//functions specific to the Turnitin plagiarism tool

/**
 * generates a url including md5 for use in posting to Turnitin API.
 *
 * @param object $tii the intial $tii object
 * @param bool $returnArray - if true, returns a formatted $tii object, if false returns a url.
 * @return mixed - array or url depending on $returnArray.
 */
function turnitin_get_url($tii, $plagiarismsettings, $returnArray=false, $pid='') {
    global $CFG,$DB;

    //make sure all $tii values are clean.
    foreach($tii as $key => $value) {
        if (!empty($value) AND $key <> 'tem' AND $key <> 'uem' AND $key <> 'dtstart' AND $key <> 'dtdue' AND $key <> 'submit_date') {
            $value = rawurldecode($value); //decode url first. (in case has already be encoded - don't want to end up with double % replacements)
            $value = rawurlencode($value);
            $value = str_replace('%20', '_', $value);
            $tii[$key] = $value;
        }
    }
    //TODO need to check lengths of certain vars. - some cannot be under 5 or over 50.
    if (isset($plagiarismsettings['turnitin_senduseremail']) && $plagiarismsettings['turnitin_senduseremail']) {
        $tii['dis'] ='0'; //sets e-mail notification for users in tii system to enabled.
    } else {
        $tii['dis'] ='1'; //sets e-mail notification for users in tii system to disabled.
    }
/*    //munge e-mails if prefix is set.
    if (!empty($plagiarismsettings['turnitin_emailprefix'])) { //if email prefix is set
        if ($tii['uem'] <> $plagiarismsettings['turnitin_email']) { //if email is not the global teacher.
            $tii['uem'] = $plagiarismsettings['turnitin_emailprefix'] . $tii['uem']; //munge e-mail to prevent user access.
        }
    }*/
    //set vars if not set.
    if (!isset($tii['encrypt'])) {
        $tii['encrypt'] = '0';
    }
    if (!isset($tii['diagnostic'])) {
        $tii['diagnostic'] = '0';
    }
    if (!isset($tii['tem'])) {
        $tii['tem'] = ''; //$plagiarismsettings['turnitin_email'];
    }
    if (!isset($tii['upw'])) {
        $tii['upw'] = '';
    }
    if (!isset($tii['cpw'])) {
        $tii['cpw'] = '';
    }
    if (!isset($tii['ced'])) {
        $tii['ced'] = '';
    }
    if (!isset($tii['dtdue'])) {
        $tii['dtdue'] = '';
    }
    if (!isset($tii['dtstart'])) {
        $tii['dtstart'] = '';
    }
    if (!isset($tii['newassign'])) {
        $tii['newassign'] = '';
    }
    if (!isset($tii['newupw'])) {
        $tii['newupw'] = '';
    }
    if (!isset($tii['oid'])) {
        $tii['oid'] = '';
    }
    if (!isset($tii['pfn'])) {
        $tii['pfn'] = '';
    }
    if (!isset($tii['pln'])) {
        $tii['pln'] = '';
    }
    if (!isset($tii['ptl'])) {
        $tii['ptl'] = '';
    }
    if (!isset($tii['ptype'])) {
        $tii['ptype'] = '';
    }
    if (!isset($tii['said'])) {
        $tii['said'] = '';
    }
    if (!isset($tii['assignid'])) {
        $tii['assignid'] = '';
    }
    if (!isset($tii['assign'])) {
        $tii['assign'] = '';
    }
    if (!isset($tii['cid'])) {
        $tii['cid'] = '';
    }
    if (!isset($tii['ctl'])) {
        $tii['ctl'] = '';
    }

    $tii['gmtime']  = turnitin_get_gmtime();
    $tii['aid']     = $plagiarismsettings['turnitin_accountid'];
    $tii['version'] = rawurlencode($CFG->release); //only used internally by TII.
    $tii['src'] = '14'; //Magic number that identifies this Integration to Turnitin 
    //prepare $tii for md5string - need to urldecode before generating the md5.
    $tiimd5 = array();
    foreach($tii as $key => $value) {
        if (!empty($value) AND $key <> 'tem' AND $key <> 'uem') {
            $value = rawurldecode($value); //decode url for calculating MD5
            $tiimd5[$key] = $value;
        } else {
            $tiimd5[$key] = $value;
        }
    }

    $tii['md5'] = turnitin_get_md5string($tiimd5, $plagiarismsettings);
    if (!empty($pid) &&!empty($tii['md5'])) {
        //save this md5 into the record.
        $tiifile = new stdClass();
        $tiifile->id = $pid;
        $tiifile->apimd5 = $tii['md5'];
        $DB->update_record('turnitin_files', $tiifile);
    }
    if ($returnArray) {
        return $tii;
    } else {
        $url = $plagiarismsettings['turnitin_api']."?";
        foreach ($tii as $key => $value) {
            $url .= $key .'='. $value. '&';
        }

        return $url;
    }
}

/**
 * internal function gets the current time formatted for use in the Turnitin Url, used by turnitin_get_url
 *
 * @return string - formatted for use in Turnitin API call.
 */
function turnitin_get_gmtime() {
    return substr(gmdate('YmdHi'), 0, -1);
}

/**
 * internal function that generates an md5 based on particular items in a $tii array - used by turnitin_get_url
 *
 * @param object $tii the intial $tii object
 * @return string - calculated md5
 */
function turnitin_get_md5string($tii, $plagiarismsettings){
    global $CFG,$DB;

    $md5string = $plagiarismsettings['turnitin_accountid'].
                $tii['assign'].
                $tii['assignid'].
                $tii['ced'].
                $tii['cid'].
                $tii['cpw'].
                $tii['ctl'].
                $tii['diagnostic'].
                $tii['dis'].
                $tii['dtdue'].
                $tii['dtstart'].
                $tii['encrypt'].
                $tii['fcmd'].
                $tii['fid'].
                $tii['gmtime'].
                $tii['newassign'].
                $tii['newupw'].
                $tii['oid'].
                $tii['pfn'].
                $tii['pln'].
                $tii['ptl'].
                $tii['ptype'].
                $tii['said'].
                $tii['tem'].
                $tii['uem'].
                $tii['ufn'].
                $tii['uid'].
                $tii['uln'].
                $tii['upw'].
                $tii['username'].
                $tii['utp'].
                $plagiarismsettings['turnitin_secretkey'];

    return md5($md5string);
}


/**
 * post data to TII
 *
 * @param object $tii - the object containing all the settings required.
 * @return xml
 */
function turnitin_post_data($tii, $plagiarismsettings, $file='', $pid='') {
    global $DB, $CFG;
    $fields = turnitin_get_url($tii, $plagiarismsettings, 'array', $pid);
    $url = get_config('plagiarism', 'turnitin_api');
    $status = check_dir_exists($CFG->dataroot."/plagiarism/",true);
    if ($status && !empty($file)) {
        if (!empty($file->type) && $file->type == "tempturnitin") {
            $fields['pdata'] = '@'.$file->filepath;
            $c = new curl(array('proxy'=>true));
            $xml = $c->post($url,$fields);
            $status = new SimpleXMLElement($xml);
        } else {
            //We cannot access the file location of $file directly - we must create a temp file to point to instead
            $filename = $CFG->dataroot."/plagiarism/".time().$file->get_filename(); //unique name for this file.
            $fh = fopen($filename,'w');
            fwrite($fh, $file->get_content());
            fclose($fh);
            $fields['pdata'] = '@'.$filename;
            $c = new curl(array('proxy'=>true));
            $status = new SimpleXMLElement($c->post($url,$fields));
            unlink($filename);
        }
    } else {
        $c = new curl(array('proxy'=>true));
        $content = $c->post($url,$fields);
        $status = new SimpleXMLElement($content);
    }
    return $status;
}

/**
 * Function that starts Turnitin session - some api calls require this
 *
 * @param object  $plagiarismsettings - from a call to plagiarism_get_settings
 * @return string - Turnitin sessionid
 */
function turnitin_start_session($user, $plagiarismsettings) {
    $tii = array();
    //set globals.
    $tii['utp']      = TURNITIN_STUDENT;
    $tii = turnitin_get_tii_user($tii, $user);
    $tii['fcmd']     = TURNITIN_RETURN_XML;
    $tii['fid']      = TURNITIN_START_SESSION;
    $content = turnitin_get_url($tii, $plagiarismsettings);
    $tiixml = plagiarism_get_xml($content);
    if (isset($tiixml->sessionid[0])) {
        return $tiixml->sessionid[0];
    } else {
        return '';
    }
}
/**
 * Function that ends a Turnitin session
 *
 * @param object  $plagiarismsettings - from a call to plagiarism_get_settings
 * @param string - Turnitin sessionid - from a call to turnitin_start_session
 */

function turnitin_end_session($user, $plagiarismsettings, $tiisession) {
    if (empty($tiisession)) {
        return;
    }
    $tii = array();
    //set globals.
    $tii['utp']      = TURNITIN_STUDENT;
    $tii = turnitin_get_tii_user($tii, $user);
    $tii['fcmd']     = TURNITIN_RETURN_XML;
    $tii['fid']      = TURNITIN_END_SESSION;
    $tii['session-id'] = $tiisession;
    $tiixml = plagiarism_get_xml(turnitin_get_url($tii, $plagiarismsettings));
}

/**
 * used to send files to turnitin for processing
 * $pid - id of this record from turnitin_files table
 * $file - contains actual file object
 *
 */
function turnitin_send_file($pid, $plagiarismsettings, $file) {
    global $DB, $CFG;
    require_once($CFG->libdir.'/filelib.php');

    //get information about this file
    $plagiarism_file = $DB->get_record('turnitin_files', array('id'=>$pid));
    $invalidrecord = false;
    if (!$user = $DB->get_record('user', array('id'=>$plagiarism_file->userid))) {
        debugging("invalid userid! - userid:".$plagiarism_file->userid." Module:".$moduletype." Fileid:".$plagiarism_file->id);
        $invalidrecord = true;
    }
    if (!$cm = $DB->get_record('course_modules', array('id'=>$plagiarism_file->cm))) {
        debugging("invalid cmid! ".$plagiarism_file->cm." Fileid:".$plagiarism_file->id);
        $invalidrecord = true;
    }
    if (!$course = $DB->get_record('course', array('id'=>$cm->course))) {
        debugging("invalid cmid! - courseid:".$cm->course." Module:".$moduletype." Fileid:".$plagiarism_file->id);
        $invalidrecord = true;
    }
    if (!$moduletype = $DB->get_field('modules','name', array('id'=>$cm->module))) {
        debugging("invalid moduleid! - moduleid:".$cm->module." Module:".$moduletype." Fileid:".$plagiarism_file->id);
        $invalidrecord = true;
    }
    if (!$module = $DB->get_record($moduletype, array('id'=>$cm->instance))) {
        debugging("invalid instanceid! - instance:".$cm->instance." Module:".$moduletype." Fileid:".$plagiarism_file->id);
        $invalidrecord = true;
    }
    if ($invalidrecord) {
        $DB->delete_records('turnitin_files', array('id'=>$plagiarism_file->id));
        return true;
    }
    $dtstart = $DB->get_record_sql('SELECT * FROM {turnitin_config} WHERE cm = ? AND '.$DB->sql_compare_text('name'). "= ?", array($cm->id, 'turnitin_dtstart'));
    if (!empty($dtstart) && $dtstart->value+600 > time()) {
        mtrace("Warning: assignment start date is too early ".date('Y-m-d H:i:s', $dtstart->value)." in course $course->shortname assignment $module->name will delay sending files until next cron");
        return false; //TODO: check that this doesn't cause a failure in cron
    }
    //Start Turnitin Session
    $tiisession = turnitin_start_session($user, $plagiarismsettings);
    //now send the file.
    $tii = array();
    $tii['utp']      = TURNITIN_STUDENT;
    $tii = turnitin_get_tii_user($tii, $user);
    $tii['cid']      = get_config('plagiarism_turnitin_course', $course->id);
    $tii['ctl']      = (strlen($course->shortname) > 45 ? substr($course->shortname, 0, 45) : $course->shortname);
    $tii['ctl']      = (strlen($tii['ctl']) > 5 ? $tii['ctl'] : $tii['ctl']."_____");
    $tii['fcmd']     = TURNITIN_RETURN_XML;
    $tii['session-id'] = $tiisession;
    //$tii2['diagnostic'] = '1';
    $tii['fid']      = TURNITIN_CREATE_USER;
    $tiixml = plagiarism_get_xml(turnitin_get_url($tii, $plagiarismsettings, false, $pid));
    if (empty($tiixml->rcode[0]) or $tiixml->rcode[0] <> TURNITIN_RESP_USER_CREATED) { //this is the success code for uploading a file. - we need to return the oid and save it!
         mtrace('could not create user/login to turnitin code:'.$tiixml->rcode[0]);
    } else {
        $plagiarism_file = $DB->get_record('turnitin_files', array('id'=>$pid)); //make sure we get latest record as it may have changed
        $plagiarism_file->statuscode = $tiixml->rcode[0];
        if (! $DB->update_record('turnitin_files', $plagiarism_file)) {
            debugging("Error updating turnitin_files record");
        }

        //now enrol user in class under the given account (fid=3)
        $sql = 'SELECT value
                FROM {turnitin_config}
                WHERE cm = ?
                AND ' . $DB->sql_compare_text('name', 255) . ' = ' . $DB->sql_compare_text('?', 255);
        $params = array($cm->id, 'turnitin_assignid');
        $turnitin_assignid = $DB->get_field_sql($sql, $params);

        if (!empty($turnitin_assignid)) {
            $tii['assignid'] = $turnitin_assignid;
        }
        $tii['assign']   = (strlen($module->name) > 90 ? substr($module->name, 0, 90) : $module->name); //assignment name stored in TII
        $tii['fid']      = TURNITIN_JOIN_CLASS;
        //$tii2['diagnostic'] = '1';
        $tiixml = plagiarism_get_xml(turnitin_get_url($tii, $plagiarismsettings, false, $pid));
        if (empty($tiixml->rcode[0]) or $tiixml->rcode[0] <> TURNITIN_RESP_USER_JOINED) { //this is the success code for uploading a file. - we need to return the oid and save it!
            mtrace('could not enrol user in turnitin class code:'.$tiixml->rcode[0]);
        } else {
            $plagiarism_file = $DB->get_record('turnitin_files', array('id'=>$pid)); //make sure we get latest record as it may have changed
            $plagiarism_file->statuscode = $tiixml->rcode[0];
            if (! $DB->update_record('turnitin_files', $plagiarism_file)) {
                debugging("Error updating turnitin_files record");
            }

            //now submit this uploaded file to Tii! (fid=5)
            $tii['fid']     = TURNITIN_SUBMIT_PAPER;
          //  if ($file->type == "tempturnitin") {
            //    $tii['ptl']     = $file->filename; //paper title
              //  $tii['submit_date'] = rawurlencode(gmdate('Y-m-d H:i:s', $file->timestamp));
           // } else {
                $tii['ptl']     = $file->get_filename(); //paper title
                $tii['submit_date'] = rawurlencode(date('Y-m-d H:i:s', $file->get_timemodified()));
           // }

            $tii['ptype']   = '2'; //filetype
            $tii['pfn']     = $tii['ufn'];
            $tii['pln']     = $tii['uln'];
            //$tii['diagnostic'] = '1';
            $tiixml = turnitin_post_data($tii, $plagiarismsettings, $file, $pid);
            if ($tiixml->rcode[0] == TURNITIN_RESP_PAPER_SENT) { //we need to return the oid and save it!
                $plagiarism_file = $DB->get_record('turnitin_files', array('id'=>$pid)); //make sure we get latest record as it may have changed
                $plagiarism_file->externalid = $tiixml->objectID[0];
                debugging("success uploading assignment", DEBUG_DEVELOPER);
            } else {
                $plagiarism_file = $DB->get_record('turnitin_files', array('id'=>$pid)); //make sure we get latest record as it may have changed
                debugging("failed to upload assignment errorcode".$tiixml->rcode[0]);
            }
            $plagiarism_file->statuscode = $tiixml->rcode[0];
            if (! $DB->update_record('turnitin_files', $plagiarism_file)) {
                debugging("Error updating turnitin_files record");
            }
            turnitin_end_session($user, $plagiarismsettings, $tiisession);

            return $tiixml;
        }
    }
}
/**
 * used to obtain similarity scores from Turnitin for submitted files.
 *
 * @param object  $plagiarismsettings - from a call to plagiarism_get_settings
 *
 */
function turnitin_get_scores($plagiarismsettings) {
    global $DB;

    mtrace("getting Turnitin scores");
    //first do submission
    //get all files set to "51" - success code for uploading.
    $files = $DB->get_records('turnitin_files',array('statuscode'=>TURNITIN_RESP_PAPER_SENT));
    if (!empty($files)) {
        foreach($files as $file) {
            //set globals.
            $user = $DB->get_record('user', array('id'=>$file->userid));
            $coursemodule = $DB->get_record('course_modules', array('id'=>$file->cm));
            $course = $DB->get_record('course', array('id'=>$coursemodule->course));
            $mainteacher = $DB->get_field('turnitin_config', 'value', array('cm'=>$file->cm, 'name'=>'turnitin_mainteacher'));
            if (!empty($mainteacher)) {
                $tii['utp']      = TURNITIN_INSTRUCTOR;
                $tii = turnitin_get_tii_user($tii, $mainteacher);
            } else {
                //check if set to never display report to student - if so we need to obtain a teacher account and use it.
                $never = $DB->get_field('turnitin_config', 'value', array('cm'=>$file->cm, 'name'=>'plagiarism_show_student_report'));
                if (empty($never)) {
                    //TODO: the student can't get at the report so we need to assign a teacher
                    debugging("ERROR: the scores can't be retrieved for courseid: ".$course->id. ", cm:".$file->cm." please edit and resave the assignment as a teacher, this will ensure all the correct settings have been made.");
                    continue;
                }
                $tii['utp']      = TURNITIN_STUDENT;
                $tii = turnitin_get_tii_user($tii, $user);
            }

            $tii['cid']      = get_config('plagiarism_turnitin_course', $course->id);
            $tii['ctl']      = (strlen($course->shortname) > 45 ? substr($course->shortname, 0, 45) : $course->shortname);
            $tii['ctl']      = (strlen($tii['ctl']) > 5 ? $tii['ctl'] : $tii['ctl']."_____");
            $tii['fcmd']     = TURNITIN_RETURN_XML;
            $tii['fid']      = TURNITIN_RETURN_REPORT;
            $tii['oid']      = $file->externalid;
            $tiixml = plagiarism_get_xml(turnitin_get_url($tii, $plagiarismsettings, false, $file->id));
            if ($tiixml->rcode[0] == TURNITIN_RESP_SCORE_RECEIVED) { //this is the success code for uploading a file. - we need to return the oid and save it!
                $file = $DB->get_record('turnitin_files', array('id'=>$file->id)); //make sure we get latest record as it may have changed
                $file->similarityscore = $tiixml->originalityscore[0];
                $file->statuscode = 'success';
                if (! $DB->update_record('turnitin_files', $file)) {
                    debugging("Error updating turnitin_files record");
                }
            } else if ($tiixml->rcode[0] == TURNITIN_RESP_SCORE_NOT_READY) {
                mtrace('similarity report not available yet for fileid:'.$file->id. " code:".$tiixml->rcode[0]);
            } else if (!empty($tiixml->rcode[0])) {
                mtrace('similarity report check failed for fileid:'.$file->id. " code:".$tiixml->rcode[0]);
                $file->statuscode = $tiixml->rcode[0];
                if (! $DB->update_record('turnitin_files', $file)) {
                    debugging("Error updating turnitin_files record");
                }
            }
        }
    }
/*    if (!empty($plagiarismsettings['turnitin_enablegrademark'])) {
        mtrace("check for external Grades");
    }*/
}


/**
 * given an error code, returns the description for this error
 * @param string statuscode The Error code.
 * @param boolean $notify if true, returns a notify call - otherwise just returns the text of the error.
 */
function turnitin_error_text($statuscode, $notify=true) {
   $return = '';
   $statuscode = (int) $statuscode;
   if (!empty($statuscode)) {
       if ($statuscode < 100) { //don't return an error state for codes 0-99
          return '';
       } else if (($statuscode > 1006 && $statuscode < 1014) or ($statuscode > 1022 && $statuscode < 1025) or $statuscode == 1020) { //these are general errors that a could be useful to students.
           $return = get_string('tiierror'.$statuscode, 'plagiarism_turnitin');
       } else if ($statuscode > 1024 && $statuscode < 2000) { //don't have documentation on the other 1000 series errors, so just display a general one.
           $return = get_string('tiierrorpaperfail', 'plagiarism_turnitin').':'.$statuscode;
       } else if ($statuscode < 1025 || $statuscode > 2000) { //these are not errors that a student can make any sense out of.
           $return = get_string('tiiconfigerror', 'plagiarism_turnitin').'('.$statuscode.')';
       }
       if (!empty($return) && $notify) {
           $return = notify($return, 'notifyproblem', 'left', true);
       }
   }
   return $return;
}

/**
 * creates/updates the assignment within Turnitin - used by event handlers.
 *
 * @param object $eventdata - data returned in an Event
 * @return boolean  returns false if unexpected error occurs.
 */
function turnitin_update_assignment($plagiarismsettings, $plagiarismvalues, $eventdata, $action) {
    global $DB;
    $result = true;
    if ($action=='delete') {
        //delete function deliberately not handled (fid=8)
        //if an assignment is deleted "accidentally" we can resotre off backups - but if
        //the external Turnitin assignment is deleted, we can't easily restore that.
        //maybe a config option could be added to enable/disable this
        return true;
    }
    //first set up this assignment/assign the global teacher to this course.
    $course = $DB->get_record('course',  array('id'=>$eventdata->courseid));
    if (empty($course)) {
        debugging("couldn't find course record - might have been deleted?", DEBUG_DEVELOPER);
        return true; //don't let this event kill cron
    }
    if (!$cm = $DB->get_record('course_modules', array('id'=>$eventdata->cmid))) {
        debugging("invalid cmid! - might have been deleted?".$eventdata->cmid, DEBUG_DEVELOPER);
        return true; //don't let this event kill cron
    }

    if (!$module = $DB->get_record($eventdata->modulename, array('id'=>$cm->instance))) {
        debugging("invalid instanceid! - instance:".$cm->instance, DEBUG_DEVELOPER);
        return true; //don't let this event kill cron
    }
    if (!$user = $DB->get_record('user', array('id'=>$eventdata->userid))) {
        debugging("invalid userid! - :".$eventdata->userid, DEBUG_DEVELOPER);
        return true; //don't let this event kill cron
    }
    $tiisession = turnitin_start_session($user, $plagiarismsettings);
    if ($action=='create' or $action=='update') { //TODO: split this into 2 - we don't need to call the create if we know it already exists.
        $tii = array();
        //set globals.
        $tii['utp']      = TURNITIN_INSTRUCTOR;
        $tii = turnitin_get_tii_user($tii, $user);
        $tii['session-id'] = $tiisession;
        $tii['ctl']      = (strlen($course->shortname) > 45 ? substr($course->shortname, 0, 45) : $course->shortname); //shouldn't happen but just in case!
        $tii['ctl']      = (strlen($tii['ctl']) > 5 ? $tii['ctl'] : $tii['ctl']."_____");
        if (get_config('plagiarism_turnitin_course', $course->id)) {
            //course already exists - don't bother to create it.
            $tii['cid']      = get_config('plagiarism_turnitin_course', $course->id); //course ID
            //mtrace('courseexists - don\'t create');
        } else {
            //TODO: use some random unique id for cid
            $tii['cid']      = "c_".time().rand(10,5000); //some unique random id only used once.
            $tii['fcmd'] = TURNITIN_RETURN_XML;
            $tii['fid']  = TURNITIN_CREATE_CLASS; // create class under the given account and assign above user as instructor (fid=2)
            $tiixml = plagiarism_get_xml(turnitin_get_url($tii, $plagiarismsettings));
            if (!empty($tiixml->rcode[0]) && ($tiixml->rcode[0] == TURNITIN_RESP_CLASS_CREATED_LOGIN or 
                                              $tiixml->rcode[0] == TURNITIN_RESP_CLASS_CREATED or 
                                              $tiixml->rcode[0] == TURNITIN_RESP_CLASS_UPDATED)) {
                //save external courseid for future reference.
                if (!empty($tiixml->classid[0])) {
                   set_config($course->id, $tii['cid'], 'plagiarism_turnitin_course');
                }
            } else {
                $result = false;
            }
        }

        if ($result) {
            //save this teacher as the "main" teacher account for this assignment, use this teacher when retrieving reports:
            if (!$DB->record_exists('turnitin_config',(array('cm'=>$cm->id, 'name'=>'turnitin_mainteacher')))) {
                $configval = new stdclass();
                $configval->cm = $cm->id;
                $configval->name = 'turnitin_mainteacher';
                $configval->value = $user->id;
                $DB->insert_record('turnitin_config', $configval);
            }
            //now create Assignment in Class
            //first check if this assignment has already been created
            if (empty($plagiarismvalues['turnitin_assignid'])) {
                $tii['assignid']   = "a_".time().rand(10,5000); //some unique random id only used once.
                $tii['fcmd'] = TURNITIN_RETURN_XML;
                // New assignments cannout have a start date/time in the past (as judged by TII servers)
                // add an hour to account for possibility of our clock being fast, or TII clock being slow.
                $tii['dtstart'] = rawurlencode(date('Y-m-d H:i:s', time()+60*60));
                $tii['dtdue'] = rawurlencode(date('Y-m-d H:i:s', time()+(365 * 24 * 60 * 60)));
            } else {
                $tii['assignid'] = $plagiarismvalues['turnitin_assignid'];
                $tii['fcmd'] = TURNITIN_UPDATE_RETURN_XML;
                if (empty($module->timeavailable)) {
                    $tii['dtstart'] = rawurlencode(date('Y-m-d H:i:s', time()));
                } else {
                    $tii['dtstart']  = rawurlencode(date('Y-m-d H:i:s', $module->timeavailable));
                }
                if (empty($module->timedue)) {
                    $tii['dtdue'] = rawurlencode(date('Y-m-d H:i:s', time()+(365 * 24 * 60 * 60)));
                } else {
                    $tii['dtdue']    = rawurlencode(date('Y-m-d H:i:s', $module->timedue));
                }
            }
            $tii['assign']   = (strlen($module->name) > 90 ? substr($module->name, 0, 90) : $module->name); //assignment name stored in TII
            $tii['fid']      = TURNITIN_CREATE_ASSIGNMENT;
            $tii['ptl']      = $course->id.$course->shortname; //paper title? - assname?
            $tii['ptype']    = TURNITIN_TYPE_FILE; //filetype
            $tii['pfn']      = $tii['ufn'];
            $tii['pln']      = $tii['uln'];

            $tii['late_accept_flag']  = (empty($module->preventlate) ? '1' : '0');
            if (isset($plagiarismvalues['plagiarism_show_student_report'])) {
                $tii['s_view_report']     = (empty($plagiarismvalues['plagiarism_show_student_report']) ? '0' : '1'); //allow students to view the full report.
            } else {
                $tii['s_view_report']     = '1';
            }
            $tii['s_paper_check']     = (isset($plagiarismvalues['plagiarism_compare_student_papers']) ? $plagiarismvalues['plagiarism_compare_student_papers'] : '1');
            $tii['internet_check']    = (isset($plagiarismvalues['plagiarism_compare_internet']) ? $plagiarismvalues['plagiarism_compare_internet'] : '1');
            $tii['journal_check']     = (isset($plagiarismvalues['plagiarism_compare_journals']) ? $plagiarismvalues['plagiarism_compare_journals'] : '1');
            $tii['institution_check'] = (isset($plagiarismvalues['plagiarism_compare_institution']) && get_config('plagiarism', 'turnitin_institutionnode') ? $plagiarismvalues['plagiarism_compare_institution'] : '0');
            $tii['report_gen_speed']  = (isset($plagiarismvalues['plagiarism_report_gen']) ? $plagiarismvalues['plagiarism_report_gen'] : '1');
            $tii['exclude_biblio']    = (isset($plagiarismvalues['plagiarism_exclude_biblio']) ? $plagiarismvalues['plagiarism_exclude_biblio'] : '0');
            $tii['exclude_quoted']    = (isset($plagiarismvalues['plagiarism_exclude_quoted']) ? $plagiarismvalues['plagiarism_exclude_quoted'] : '0');
            $tii['exclude_type']      = (isset($plagiarismvalues['plagiarism_exclude_matches']) ? $plagiarismvalues['plagiarism_exclude_matches'] : '0');
            $tii['exclude_value']     = (isset($plagiarismvalues['plagiarism_exclude_matches_value']) ? $plagiarismvalues['plagiarism_exclude_matches_value'] : '');
            $tii['ainst']             = (!empty($module->intro) ? $module->intro : '');
            $tii['max_points']        = (!empty($module->grade) && $module->grade > 0 ? ceil($module->grade) : '0');
            if (isset($plagiarismvalues['plagiarism_anonymity'])) {
                $tii['anon'] = $plagiarismvalues['plagiarism_anonymity'] ? 1 : 0;
            }
            //$tii['diagnostic'] = '1'; //debug only - uncomment when using in production.

            $tiixml = turnitin_post_data($tii, $plagiarismsettings);
            if ($tiixml->rcode[0]==TURNITIN_RESP_ASSIGN_EXISTS) { //if assignment already exists then update it and set externalassignid correctly
                $tii['fcmd'] = TURNITIN_UPDATE_RETURN_XML; //when set to 3 - it updates the course
                $tiixml = turnitin_post_data($tii, $plagiarismsettings);
            }
            if ($tiixml->rcode[0]==TURNITIN_RESP_ASSIGN_CREATED) {
                //save assid for use later.
                if (!empty($tiixml->assignmentid[0])) {
                    if (empty($plagiarismvalues['turnitin_assignid'])) {
                        $configval = new stdclass();
                        $configval->cm = $cm->id;
                        $configval->name = 'turnitin_assignid';
                        $configval->value = $tiixml->assignmentid[0];
                        $DB->insert_record('turnitin_config', $configval);
                    } else {
                        $configval = $DB->get_record('turnitin_config', array('cm'=> $cm->id, 'name'=> 'turnitin_assignid'));
                        $configval->value = $tiixml->assignmentid[0];
                        $DB->update_record('turnitin_config', $configval);
                    }
                }
                if (!empty($module->timedue) or (!empty($module->timeavailable))) {
                    //need to update time set in Turnitin.
                    $tii['assignid'] = $tiixml->assignmentid[0];
                    $tii['fcmd'] = TURNITIN_UPDATE_RETURN_XML;
                    if (!empty($module->timeavailable)) {
                        $tii['dtstart']  = rawurlencode(date('Y-m-d H:i:s', $module->timeavailable));
                    }
                    if (!empty($module->timedue)) {
                        $tii['dtdue']    = rawurlencode(date('Y-m-d H:i:s', $module->timedue));
                    }
                }
                $tiixml = turnitin_post_data($tii, $plagiarismsettings);
            }
            if ($tiixml->rcode[0]==TURNITIN_RESP_ASSIGN_MODIFIED) {
                mtrace("Turnitin Success creating Class and assignment");
            } else {
                mtrace("Error: could not create assignment in class statuscode:".$tiixml->rcode[0]);
                $return = false;
            }

        } else {
            mtrace("Error: could not create class and assign global instructor statuscode:".$tiixml->rcode[0]);
            $return = false;
        }
    }
    turnitin_end_session($user, $plagiarismsettings, $tiisession);
    return $result;
}

/**
 * returns link to grademark for a file.
 * this function assumes that another process has already updated the grademark status
 *
 * @param object $plagiarismfile - record from plagiarsim_files table
 * @param object $course - course record
 * @param object $course - module record
 * @param object  $plagiarismsettings - from a call to plagiarism_get_settings
 * @return string - link to grademark function including images.
 */
function turnitin_get_grademark_link($plagiarismfile, $course, $module, $plagiarismsettings) {
    global $DB, $CFG, $OUTPUT, $USER;
    $output = '';
    //first check the grademark status - don't show link if not enabled
    if (empty($plagiarismsettings['turnitin_enablegrademark'])) {
        return $output;
    }
    if (empty($plagiarismfile->externalstatus) ||
       ($USER->id <> $plagiarismfile->userid && !empty($module->timedue) && $module->timedue > time())) {
        //Grademark isn't available yet - don't provide link
        $output = '<img src="'.$OUTPUT->pix_url('i/grademark-grey').'">';
    } else {
        $tii = array();
        if (!has_capability('mod/assignment:grade', get_context_instance(CONTEXT_MODULE, $plagiarismfile->cm))) {
            $tii['utp']      = TURNITIN_STUDENT;
            $tii = turnitin_get_tii_user($tii, $USER);
        } else {
            $tii['utp']      = TURNITIN_INSTRUCTOR;
            $tii = turnitin_get_tii_user($tii, $USER);
        }
        $tii['cid']      = get_config('plagiarism_turnitin_course', $course->id);
        $tii['ctl']      = (strlen($course->shortname) > 45 ? substr($course->shortname, 0, 45) : $course->shortname);
        $tii['ctl']      = (strlen($tii['ctl']) > 5 ? $tii['ctl'] : $tii['ctl']."_____");
        $tii['fcmd'] = TURNITIN_LOGIN;
        $tii['fid'] = TURNITIN_RETURN_GRADEMARK;
        $tii['oid'] = $plagiarismfile->externalid;
        $output = '<a href="'.turnitin_get_url($tii, $plagiarismsettings).'" target="_blank"><img src="'.$OUTPUT->pix_url('i/grademark').'"></a>';
    }
    return $output;
}
/**
 * Function that returns turnaround time for reports from Turnitin
 *
 * @param object  $plagiarismsettings - from a call to plagiarism_get_settings
 * @return xml - xml
 */
function turnitin_get_responsetime($plagiarismsettings) {
    global $USER;
    $tii = array();
    //set globals.
    $tii['utp']      = TURNITIN_INSTRUCTOR;
    $tii = turnitin_get_tii_user($tii, $USER);
    $tii['fcmd']     = TURNITIN_RETURN_XML;
    $tii['fid']      = TURNITIN_REPORT_TIME;
    $tiixml = plagiarism_get_xml(turnitin_get_url($tii, $plagiarismsettings));
    return $tiixml;
}
/**
 * adds the list of plagiarism settings to a form
 *
 * @param object $mform - Moodle form object
 */
function turnitin_get_form_elements($mform) {
        $ynoptions = array( 0 => get_string('no'), 1 => get_string('yes'));
        $tiishowoptions = array(PLAGIARISM_TII_SHOW_NEVER => get_string("never"), PLAGIARISM_TII_SHOW_ALWAYS => get_string("always"), PLAGIARISM_TII_SHOW_CLOSED => get_string("showwhenclosed", "plagiarism_turnitin"));
        $tiidraftoptions = array(0 => get_string("submitondraft","plagiarism_turnitin"), 1 => get_string("submitonfinal","plagiarism_turnitin"));
        $reportgenoptions = array( 0 => get_string('reportgenimmediate', 'plagiarism_turnitin'), 1 => get_string('reportgenimmediateoverwrite', 'plagiarism_turnitin'), 2 => get_string('reportgenduedate', 'plagiarism_turnitin'));
        $excludetype = array( 0 => get_string('no'), 1 => get_string('wordcount', 'plagiarism_turnitin'), 2 => get_string('percentage', 'plagiarism_turnitin'));

        $mform->addElement('header', 'plagiarismdesc');
        $mform->addElement('select', 'use_turnitin', get_string("useturnitin", "plagiarism_turnitin"), $ynoptions);
        $mform->addElement('select', 'plagiarism_show_student_score', get_string("showstudentsscore", "plagiarism_turnitin"), $tiishowoptions);
        $mform->addHelpButton('plagiarism_show_student_score', 'showstudentsscore', 'plagiarism_turnitin');
        $mform->addElement('select', 'plagiarism_show_student_report', get_string("showstudentsreport", "plagiarism_turnitin"), $tiishowoptions);
        $mform->addHelpButton('plagiarism_show_student_report', 'showstudentsreport', 'plagiarism_turnitin');
        if ($mform->elementExists('var4')) {
            $mform->addElement('select', 'plagiarism_draft_submit', get_string("draftsubmit", "plagiarism_turnitin"), $tiidraftoptions);
        }
        $mform->addElement('select', 'plagiarism_compare_student_papers', get_string("comparestudents", "plagiarism_turnitin"), $ynoptions);
        $mform->addHelpButton('plagiarism_compare_student_papers', 'comparestudents', 'plagiarism_turnitin');
        $mform->addElement('select', 'plagiarism_compare_internet', get_string("compareinternet", "plagiarism_turnitin"), $ynoptions);
        $mform->addHelpButton('plagiarism_compare_internet', 'compareinternet', 'plagiarism_turnitin');
        $mform->addElement('select', 'plagiarism_compare_journals', get_string("comparejournals", "plagiarism_turnitin"), $ynoptions);
        $mform->addHelpButton('plagiarism_compare_journals', 'comparejournals', 'plagiarism_turnitin');
        if (get_config('plagiarism', 'turnitin_institutionnode')) {
            $mform->addElement('select', 'plagiarism_compare_institution', get_string("compareinstitution", "plagiarism_turnitin"), $ynoptions);
            $mform->addHelpButton('plagiarism_compare_institution', 'compareinstitution', 'plagiarism_turnitin');
        }
        $mform->addElement('select', 'plagiarism_report_gen', get_string("reportgen", "plagiarism_turnitin"), $reportgenoptions);
        $mform->addHelpButton('plagiarism_report_gen', 'reportgen', 'plagiarism_turnitin');
        $mform->addElement('select', 'plagiarism_exclude_biblio', get_string("excludebiblio", "plagiarism_turnitin"), $ynoptions);
        $mform->addHelpButton('plagiarism_exclude_biblio', 'excludebiblio', 'plagiarism_turnitin');
        $mform->addElement('select', 'plagiarism_exclude_quoted', get_string("excludequoted", "plagiarism_turnitin"), $ynoptions);
        $mform->addHelpButton('plagiarism_exclude_quoted', 'excludequoted', 'plagiarism_turnitin');
        $mform->addElement('select', 'plagiarism_exclude_matches', get_string("excludematches", "plagiarism_turnitin"), $excludetype);
        $mform->addHelpButton('plagiarism_exclude_matches', 'excludematches', 'plagiarism_turnitin');
        $mform->addElement('text', 'plagiarism_exclude_matches_value', '');
        $mform->addRule('plagiarism_exclude_matches_value', null, 'numeric', null, 'client');
        $mform->disabledIf('plagiarism_exclude_matches_value', 'plagiarism_exclude_matches', 'eq', 0);
        $mform->addElement('select', 'plagiarism_anonymity', get_string("anonymity", "plagiarism_turnitin"), $ynoptions);
        $mform->addHelpButton('plagiarism_anonymity', 'anonymity', 'plagiarism_turnitin');
}

/**
 * generates a url to allow access to a similarity report. - helper functino for plagiarism_get_link
 *
 * @param object  $file - single record from turnitin_files table
 * @param object  $course - usually global $COURSE value
 * @param array  $plagiarismsettings - from a call to plagiarism_get_settings
 * @return string - url to allow login/viewing of a similarity report
 */
function turnitin_get_report_link($file, $course, $plagiarismsettings) {
    global $DB, $USER;
    $return = '';

    $tii = array();
    if (!has_capability('mod/assignment:grade', get_context_instance(CONTEXT_MODULE, $file->cm))) {
        $tii['utp']      = TURNITIN_STUDENT;
    } else {
        $tii['utp']      = TURNITIN_INSTRUCTOR;
    }
    $tii = turnitin_get_tii_user($tii, $USER);
    $tii['cid']      = get_config('plagiarism_turnitin_course', $course->id);
    $tii['ctl']      = (strlen($course->shortname) > 45 ? substr($course->shortname, 0, 45) : $course->shortname);
    $tii['ctl']      = (strlen($tii['ctl']) > 5 ? $tii['ctl'] : $tii['ctl']."_____");
    $tii['fcmd'] = TURNITIN_LOGIN;
    $tii['fid'] = TURNITIN_RETURN_REPORT;
    $tii['oid'] = $file->externalid;

    return turnitin_get_url($tii, $plagiarismsettings);
}
/**
 * internal function that returns xml when provided a URL
 *
 * @param string $url the url being passed.
 * @return xml
 */
function plagiarism_get_xml($url) {
    global $CFG;
    require_once($CFG->libdir."/filelib.php");
    if (!($fp = download_file_content($url))) {
        print_error('fileopenerror', 'plagiarism_turnitin', '', $url);
    } else {
            //now do something with the XML file to check to see if this has worked!
        $xml = new SimpleXMLElement($fp);
        return $xml;
    }
}



/**
 * updates a turnitin_files record
 *
 * @param int $cmid  - course module id
 * @param int $userid  - user id
 * @param varied $identifier  - identifier for this plagiarism record - hash of file, id of quiz question etc
 * @return int - id of turnitin_files record
 */
function plagiarism_update_record($cmid, $userid, $identifier, $attempt=0) {
    global $DB;
    if (empty($identifier)) {
        mtrace("error - no identifier passed - could not update plagiarism record!");
        return false;
    }
    //now update or insert record into turnitin_files
    $plagiarism_file = $DB->get_record_sql(
                                "SELECT * FROM {turnitin_files}
                                 WHERE cm = ? AND userid = ? AND " .
                                $DB->sql_compare_text('identifier') . " = ?",
                                array($cmid, $userid,$identifier));
    if (!empty($plagiarism_file)) {
        //update record.
        //only update this record if it isn't pending or in a success state
        //TODO: this only works with files - need to allow force update for things like quiz essay qs
        if ($plagiarism_file->statuscode != 'pending' &&
            $plagiarism_file->statuscode != 'success' &&
            $plagiarism_file->statuscode != TURNITIN_RESP_PAPER_SENT) {
            $plagiarism_file->statuscode = 'pending';
            $plagiarism_file->similarityscore ='0';
            $plagiarism_file->attempt = $attempt;
            $DB->update_record('turnitin_files', $plagiarism_file);
            return $plagiarism_file->id;
        }
    } else {
        $plagiarism_file = new object();
        $plagiarism_file->cm = $cmid;
        $plagiarism_file->userid = $userid;
        $plagiarism_file->identifier = $identifier;
        $plagiarism_file->statuscode = 'pending';
        $plagiarism_file->attempt = $attempt;
        if (!$pid = $DB->insert_record('turnitin_files', $plagiarism_file)) {
            debugging("insert into turnitin_files failed");
        }
        return $pid;
    }
    return false;
}


/**
* Function that returns the name of the css class to use for a given similarity score
* @param integer $score - the similarity score
* @return string - string name of css class
*/
function plagiarism_get_css_rank ($score) {
    $rank = "none";
    if($score >  90) { $rank = "1"; }
    else if($score >  80) { $rank = "2"; }
    else if($score >  70) { $rank = "3"; }
    else if($score >  60) { $rank = "4"; }
    else if($score >  50) { $rank = "5"; }
    else if($score >  40) { $rank = "6"; }
    else if($score >  30) { $rank = "7"; }
    else if($score >  20) { $rank = "8"; }
    else if($score >  10) { $rank = "9"; }
    else if($score >=  0) { $rank = "10"; }

    return "rank$rank";
}
/**
* Function used to add the user details to a Turnitin call
* @param $tii array() $tii array passed to a get_url call
* @param $plagiarismsettings array()  - plagiarism settings array
* @return string - string name of css class
*/
function turnitin_get_tii_user($tii, $user) {
    global $USER, $DB;
    if (is_number($user)) {
        //full user record needed
        $user = ($user == $USER->id ? $USER : $DB->get_record('user', array('id'=>$user)));
    }
    $tii['username'] = $user->username;
    $tii['uem']      = $user->email;
    $tii['ufn']      = $user->firstname;
    $tii['uln']      = $user->lastname;
    $tii['uid']      = $user->username;

    return $tii;
}

function turnitin_event_file_uploaded($eventdata) {
    $eventdata->eventtype = 'file_uploaded';
    $turnitin = new plagiarism_plugin_turnitin();
    return $turnitin->event_handler($eventdata);
}
function turnitin_event_files_done($eventdata) {
    $eventdata->eventtype = 'file_uploaded';
    $turnitin = new plagiarism_plugin_turnitin();
    return $turnitin->event_handler($eventdata);
}

function turnitin_event_mod_created($eventdata) {
    $eventdata->eventtype = 'mod_created';
    $turnitin = new plagiarism_plugin_turnitin();
    return $turnitin->event_handler($eventdata);
}

function turnitin_event_mod_updated($eventdata) {
    $eventdata->eventtype = 'mod_updated';
    $turnitin = new plagiarism_plugin_turnitin();
    return $turnitin->event_handler($eventdata);
}

function turnitin_event_mod_deleted($eventdata) {
    $eventdata->eventtype = 'mod_deleted';
    $turnitin = new plagiarism_plugin_turnitin();
    return $turnitin->event_handler($eventdata);
}
