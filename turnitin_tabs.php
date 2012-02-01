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

    $strplagiarism = get_string('turnitin', 'plagiarism_turnitin');
    $strplagiarismdefaults = get_string('turnitindefaults', 'plagiarism_turnitin');
    $strplagiarismerrors = get_string('turnitinerrors', 'plagiarism_turnitin');

    $tabs = array();
    $tabs[] = new tabobject('turnitinsettings', 'settings.php', $strplagiarism, $strplagiarism, false);
    $tabs[] = new tabobject('turnitindefaults', 'turnitin_defaults.php', $strplagiarismdefaults, $strplagiarismdefaults, false);
    $tabs[] = new tabobject('turnitinerrors', 'turnitin_errors.php', $strplagiarismerrors, $strplagiarismerrors, false);
    print_tabs(array($tabs), $currenttab);