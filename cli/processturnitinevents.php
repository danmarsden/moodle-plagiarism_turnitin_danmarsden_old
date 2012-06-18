<?php
define('CLI_SCRIPT', true);

require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->dirroot.'/lib/clilib.php'); // cli only functions
require_once($CFG->dirroot.'/lib/cronlib.php');
require_once($CFG->dirroot.'/lib/eventslib.php');
require_once($CFG->dirroot.'/lib/filelib.php');

// suppress
$CFG->debug = DEBUG_NONE;
$CFG->debugdisplay = false;
// increase time limit
set_time_limit(0);
$starttime = microtime();
/// increase memory limit
raise_memory_limit(MEMORY_EXTRA);
/// Start output log
$timenow  = time();

mtrace("Server Time: ".date('r',$timenow));

$processed = plagiarism_turnitin_process_events_cron('mod_created');
mtrace('mod_created, processed: '.$processed);

$processed = plagiarism_turnitin_process_events_cron('mod_updated');
mtrace('mod_updated, processed: '.$processed);

$processed = plagiarism_turnitin_process_events_cron('mod_deleted');
mtrace('mod_deleted, processed: '.$processed);

$processed = plagiarism_turnitin_process_events_cron('assessable_file_uploaded');
mtrace('assessable_file_uploaded, processed: '.$processed);

$processed = plagiarism_turnitin_process_events_cron('assessable_files_done');
mtrace('assessable_files_done, processed: '.$processed);

$difftime = microtime_diff($starttime, microtime());

mtrace("Execution took ".$difftime." seconds");

function plagiarism_turnitin_process_events_cron($eventname) {
    global $DB;

    $failed = array();
    $processed = 0;

    $sql = "SELECT qh.*
              FROM {events_queue_handlers} qh, {events_handlers} h 
             WHERE qh.handlerid = h.id 
               AND h.eventname = ? 
          ORDER BY qh.id";
    $params = array($eventname);
    $rs = $DB->get_recordset_sql($sql, $params);
    foreach ($rs as $qhandler) {
        if (isset($failed[$qhandler->handlerid])) {
            // do not try to dispatch any later events when one already asked for retry or ended with exception
            //continue;
        }
        $status = events_process_queued_handler($qhandler);
        if ($status === false) {
            // handler is asking for retry, do not send other events to this handler now
            $failed[$qhandler->handlerid] = $qhandler->handlerid;
        } else if ($status === NULL) {
            // means completely broken handler, event data was purged
            $failed[$qhandler->handlerid] = $qhandler->handlerid;
        } else {
            $processed++;
        }
    }
    $rs->close();

    // remove events that do not have any handlers waiting
    $sql = "SELECT eq.id
              FROM {events_queue} eq
              LEFT JOIN {events_queue_handlers} qh ON qh.queuedeventid = eq.id
             WHERE qh.id IS NULL";
    $rs = $DB->get_recordset_sql($sql);
    foreach ($rs as $event) {
        $DB->delete_records('events_queue', array('id'=>$event->id));
    }
    $rs->close();
    
    return $processed;
}

?>
