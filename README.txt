Moodle Turnitin Plagiarism Plugin written by Dan Marsden <dan@danmarsden.com>

for installation/configuration information see:
http://docs.moodle.org/en/Plagiarism_Prevention_Turnitin

KNOWN ISSUE WITH Moodle 2.0-2.2 (fixed in 2.3)
================
When running cron this error occurs:
PHP Fatal error:  plagiarism_plugin ::event_handler(): The script tried to execute a method or access a property
of an incomplete object. Please ensure that the class definition "stored_file" of the object you are trying to
operate on was loaded _before_ unserialize() gets called or provide a __autoload() function to load the class definition

The fix is to make a change to lib/cronlib.php. -find these lines:
    mtrace('Starting processing the event queue...');
    events_cron();
    mtrace('done.');
and replace them with this:
    mtrace('Starting processing the event queue...');
    require_once($CFG->libdir.'/filelib.php');
    events_cron();
    mtrace('done.');