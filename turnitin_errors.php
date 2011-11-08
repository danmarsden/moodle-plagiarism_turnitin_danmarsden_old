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
 * turnitin_errors.php - Displays Turnitin files with a current error state.
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

require_login();
admin_externalpage_setup('plagiarismturnitin');

$id = optional_param('id',0,PARAM_INT);
$resetuser = optional_param('reset',0,PARAM_INT);
$delete = optional_param('delete', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$sort = optional_param('sort', '', PARAM_ALPHA);
$dir = optional_param('dir', '', PARAM_ALPHA);

$limit = 20;
$baseurl = new moodle_url('turnitin_errors.php', array('page' => $page, 'sort' => $sort));

echo $OUTPUT->header();
$currenttab='turnitinerrors';
require_once('turnitin_tabs.php');

echo $OUTPUT->box(get_string('tiiexplainerrors','plagiarism_turnitin'));
$sqlallfiles = "SELECT t.*, u.firstname, u.lastname, m.name as moduletype, ".
               "cm.course as courseid, cm.instance as cminstance FROM ".
               "{turnitin_files} t, {user} u, {modules} m, {course_modules} cm ".
               "WHERE m.id=cm.module AND cm.id=t.cm AND t.userid=u.id ".
               "AND t.statuscode <>'success' AND t.statuscode <>'pending' AND t.statuscode <> '51'";
$sqlcount =  "SELECT COUNT(id) FROM {turnitin_files} WHERE statuscode <>'success' AND statuscode <>'pending' AND statuscode <> '51'";
if ($resetuser==1 && $id) {
    $sqlid = "SELECT t.*, u.firstname, u.lastname, u.id as userid, m.name as moduletype, cm.course as courseid, cm.instance as cminstance FROM {turnitin_files} t, {user} u, {modules} m, {course_modules} cm WHERE m.id=cm.module AND cm.id=t.cm AND t.userid=u.id AND t.id = ?";
    $tfile = $DB->get_record_sql($sqlid, array('id'=>$id));
    $tfile->statuscode = 'pending';
    $modulecontext = get_context_instance(CONTEXT_MODULE, $tfile->cm);
    if ($tfile->moduletype =='assignment') {
         $submission = $DB->get_record('assignment_submissions', array('assignment'=>$tfile->cminstance, 'userid'=>$tfile->userid));
         $fs = get_file_storage();
         $files = $fs->get_area_files($modulecontext->id, 'mod_assignment', 'submission', $submission->id);
         if (!empty($files)) {
             $eventdata = new stdClass();
             $eventdata->modulename   = $tfile->moduletype;
             $eventdata->cmid         = $tfile->cm;
             $eventdata->courseid     = $tfile->courseid;
             $eventdata->userid       = $tfile->userid;
             $eventdata->files        = $files;

             events_trigger('assessable_file_uploaded', $eventdata);
             //now reset status so that it disapears from error page
             if ($DB->update_record('turnitin_files', $tfile)) {
                 notify(get_string('fileresubmitted','plagiarism_turnitin'));
             }
         } else {
             notify("could not find any files for this submission");
         }
    } else {
        notify("resubmit function for ".$tfile->moduletype. " not complete yet"); 
    }
    //TODO: trigger event for this file

} elseif ($resetuser==2) {
    $tiifiles = get_records_sql($sqlallfiles);
    foreach($tiifiles as $tiifile) {
        $tiifile->statuscode = 'pending';
        //TODO: trigger event for this file
       // if ($DB->update_record('turnitin_files', $tiifile)) {
         //   notify(get_string('fileresubmitted','plagiarism_turnitin'));
        //}
    }
 }
if (!empty($delete)) {
    $DB->delete_records('turnitin_files', array('id'=>$id));
    notify(get_string('filedeleted','plagiarism_turnitin'));

}
//now do sorting if specified
$orderby = '';
if (!empty($sort)) {
    if ($sort=="name") {
        $orderby = " ORDER BY u.firstname, u.lastname";
    } elseif ($sort=="module") {
        $orderby = " ORDER BY cm.id";
    } elseif ($sort=="status") {
        $orderby = " ORDER BY t.statuscode";
    }
    if (!empty($orderby) && ($dir=='asc' || $dir=='desc')) {
        $orderby .= " ".$dir;
    }
}

$count = $DB->count_records_sql($sqlcount);

$turnitin_files = $DB->get_records_sql($sqlallfiles.$orderby, null, $page*$limit, $limit);


$table = new html_table();
$columns = array('name', 'module', 'file', 'status');
foreach ($columns as $column) {
    $strtitle = get_string($column, 'plagiarism_turnitin');
    if ($column=='file') {
        $table->head[] = $strtitle;
    } else {
        if ($sort != $column) {
            $columnicon = '';
            $columndir = 'asc';
        } else {
            $columndir = $dir == 'asc' ? 'desc' : 'asc';
            $columnicon = ' <img src="'.$OUTPUT->pix_url('t/'.($dir == 'asc' ? 'down' : 'up' )).'f" alt="" />';
        }
        $table->head[] = '<a href="turnitin_errors.php?sort='.$column.'&amp;dir='.$columndir.'">'.$strtitle.'</a>'.$columnicon;
    }
    $table->align[] = 'left';
}
$table->head[] = '';

$table->width = "95%";
foreach ($turnitin_files as $tf) {
    //heavy way to obtain filename and object to allow download link:
    //TODO: there must be a nicer way to do this.
    $modulecontext = get_context_instance(CONTEXT_MODULE, $tf->cm);
    $submission = $DB->get_record('assignment_submissions', array('assignment'=>$tf->cminstance, 'userid'=>$tf->userid));
    $fs = get_file_storage();
    $files = $fs->get_area_files($modulecontext->id, 'mod_assignment', 'submission', $submission->id);
    $filelink = $tf->identifier;
    if (!empty($files)) {
        foreach($files as $file) {
            if ($file->get_contenthash()==$tf->identifier) {
                $filelink = '<a href="'.$CFG->wwwroot.'/files/">'.$file->get_filename()."</a>";

                $url = file_encode_url("$CFG->wwwroot/pluginfile.php", '/'.$modulecontext->id.'/mod_assignment/submission/'.$file->get_itemid(). $file->get_filepath().$file->get_filename(), true);
                $filelink = html_writer::link($url, $file->get_filename());
            }
        }
    }


    $user = "<a href='".$CFG->wwwroot."/user/profile.php?id=".$tf->userid."'>".fullname($tf)."</a>";
    
    $reset = '<a href="turnitin_errors.php?reset=1&id='.$tf->id.'">'.get_string('resubmit','plagiarism_turnitin').'</a> | '.
             '<a href="turnitin_errors.php?delete=1&id='.$tf->id.'">'.get_string('delete').'</a>';
    $cmlink = '<a href="'.$CFG->wwwroot.'/'.$tf->moduletype.'/view.php?id='.$tf->cm.'">'.get_string('module', 'plagiarism_turnitin').'</a>';
    $table->data[] = array ($user,
                            $cmlink,
                            $filelink,
                            $tf->statuscode,
                            $reset);
}
    if (!empty($table)) {
        echo html_writer::table($table);
        echo $OUTPUT->paging_bar($count, $page, $limit, $baseurl);
    }

echo $OUTPUT->footer();
