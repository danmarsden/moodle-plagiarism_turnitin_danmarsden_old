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

require_login();
admin_externalpage_setup('plagiarismturnitin');
$PAGE->set_url('/plagiarism/turnitin/migrateassignment22.php');

echo $OUTPUT->header();
$currenttab='turnitinmigrate';
require_once('turnitin_tabs.php');

echo $OUTPUT->box(get_string('turnitinmigrate_help', 'plagiarism_turnitin'));

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
