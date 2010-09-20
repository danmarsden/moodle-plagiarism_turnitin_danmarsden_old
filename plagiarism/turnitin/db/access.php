<?php

$capabilities = array(

    'moodle/plagiarism_turnitin:enable' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
         'legacy' => array(
         'editingteacher' => CAP_ALLOW,
         'manager' => CAP_ALLOW
        )
    ),

    'moodle/plagiarism_turnitin:viewsimilarityscore' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
         'legacy' => array(
         'editingteacher' => CAP_ALLOW,
         'manager' => CAP_ALLOW
        )
    ),

    'moodle/plagiarism_turnitin:viewfullreport' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
         'legacy' => array(
         'editingteacher' => CAP_ALLOW,
         'manager' => CAP_ALLOW
        )
    ),
);
