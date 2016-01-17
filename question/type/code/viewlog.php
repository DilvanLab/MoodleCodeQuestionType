<?php

require_once(dirname(__FILE__) . '/../../../config.php');
require_once(dirname(__FILE__) . '/../../editlib.php');
require_once($CFG->libdir . '/filelib.php');

$contextid = required_param('contextid', PARAM_INT);
$runid = required_param('runid', PARAM_TEXT);

$outputNames = ["log", "output", "feedback"];

$fs = get_file_storage();
echo '<style> h1 {font-family: "Helvetica Neue", "Helvetica", "Arial", sans-serif; } </style>';
foreach($outputNames as $v) {
    echo "<h1>Output <span style='font-family: monospace'>'$v'</span></h1>";

    $file = $fs->get_file($contextid, 'qcode', 'runs', 0, '/', "$runid-$v.txt");
    echo "<pre>";
    echo $file->get_content();
    echo "</pre>";
}