<?php

$capabilities = array(

    'plagiarism/turnitin:enable' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
         'legacy' => array(
         'editingteacher' => CAP_ALLOW,
         'manager' => CAP_ALLOW
        )
    ),

    'plagiarism/turnitin:viewsimilarityscore' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
         'legacy' => array(
         'editingteacher' => CAP_ALLOW,
         'manager' => CAP_ALLOW
        )
    ),

    'plagiarism/turnitin:viewfullreport' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
         'legacy' => array(
         'editingteacher' => CAP_ALLOW,
         'manager' => CAP_ALLOW
        )
    ),
);
