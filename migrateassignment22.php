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
 * migrateassignment22.php - migrates Assignment 2.2 settings to upgraded 2.3 assignments. DODGY HACK WARNING!
 *
 * @package   plagiarism_turnitin
 * @author    Dan Marsden <dan@danmarsden.com>
 * @copyright 2012 Dan Marsden
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(__FILE__)) . '/../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/plagiarismlib.php');
require_once($CFG->dirroot.'/plagiarism/turnitin/lib.php');
require_once($CFG->dirroot.'/plagiarism/turnitin/migratelib.php');

$fix19assignments = optional_param('fix19', 0, PARAM_INT);

require_login();
admin_externalpage_setup('plagiarismturnitin');
$PAGE->set_url('/plagiarism/turnitin/migrateassignment22.php');

echo $OUTPUT->header();
echo $OUTPUT->notification("NASTY HACK ALERT - this script is a nasty hack that uses the logs table to translate old assignment ids to new 2.3 assignment ids - use at own risk!!!!");
echo $OUTPUT->notification("THIS SCRIPT IS NOT COMPLETE - the files hashes still point to the old 2.2 path - a script needs to be written to translate the old hash into the new path hash so that similarity score links will be maintained.");
$currenttab='turnitinmigrate';
require_once('turnitin_tabs.php');

echo $OUTPUT->box(get_string('turnitinmigrate_help', 'plagiarism_turnitin'));

if ($fix19assignments) { //script to fix 19 legacy assignments that weren't migrated in 2.2
    turnitin_fix19assignments();
}

if ((data_submitted()) && confirm_sesskey()) {
    turnitin_migratecmids();
    echo $OUTPUT->notification(get_string('migrated', 'plagiarism_turnitin'), 'notifysuccess');
} else {
    echo $OUTPUT->single_button($PAGE->url, get_string('turnitinmigrate', 'plagiarism_turnitin'));
}

echo $OUTPUT->footer();
