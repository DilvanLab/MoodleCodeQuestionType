<?php
define('AJAX_SCRIPT', true);

require_once(dirname(__FILE__) . '/../../../config.php');
require_once(dirname(__FILE__) . '/../../editlib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/formslib.php');
require_once('questiontype.php');

$courseid = required_param('courseid', PARAM_INT);
$envid = required_param('env', PARAM_ALPHA);


// check permissions
$thiscontext = context_course::instance($courseid);

$addpermission = has_capability('moodle/question:add', $thiscontext);

$qtypeobj = new qtype_code();

$env = $qtypeobj->get_env($envid);

if(!$addpermission || !$env) {
    echo json_encode(null);
} else {
    echo json_encode($env->getOptions());
}