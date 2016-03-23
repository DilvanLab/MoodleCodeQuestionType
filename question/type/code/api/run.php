<?php
define('AJAX_SCRIPT', true);

require_once('../../../../config.php');
require("../../../engine/bank.php");

function mc_response($data) {
    if($data) {
        echo json_encode($data);
    } else {
        echo json_encode(null);
    }
}

function mc_error($message) {
    mc_response([
        "error" => [
            "message" => $message
        ]
    ]);
}

try {
    /** @var qtype_code_question $question */
    $question = question_bank::load_question($_REQUEST['questionid']);
} catch (Exception $err) {
    mc_error("Can't load question definition");
    die();
}

$env = $question->loadEnv();
$graded = $env->grade($_REQUEST);

mc_response([
    "results" => [
        "success" => $graded['success'],
        "feedback" => $graded['output']['feedback']
    ]
]);
