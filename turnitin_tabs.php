<?php
    $strplagiarism = get_string('turnitin', 'plagiarism_turnitin');
    $strplagiarismdefaults = get_string('turnitindefaults', 'plagiarism_turnitin');
    $strplagiarismerrors = get_string('turnitinerrors', 'plagiarism_turnitin');

    $tabs = array();
    $tabs[] = new tabobject('turnitinsettings', 'settings.php', $strplagiarism, $strplagiarism, false);
    $tabs[] = new tabobject('turnitindefaults', 'turnitin_defaults.php', $strplagiarismdefaults, $strplagiarismdefaults, false);
    $tabs[] = new tabobject('turnitinerrors', 'turnitin_errors.php', $strplagiarismerrors, $strplagiarismerrors, false);
    print_tabs(array($tabs), $currenttab);