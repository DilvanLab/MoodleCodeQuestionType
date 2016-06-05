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
 * code question definition class.
 *
 * @package    qtype
 * @subpackage code
 * @copyright  2015 João Pedro Finoto (joao.finoto.martins@usp.br)

 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once("graded.class.php");


/**
 * Represents a code question.
 *
 * @copyright  2015 João Pedro Finoto (joao.finoto.martins@usp.br)

 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_code_question extends question_graded_automatically implements question_automatically_gradable {

    public $responselang;
    public $envoptions;
    public $autocorrectenv;
    /**
     * Contains the graded result
     * @var array
     */
    public $graded;
    /** @var env */
    public $env = null;

    public function __construct() {

    }

//    public function make_behaviour(question_attempt $qa, $preferredbehaviour) {
//        //return question_engine::make_behaviour('deferredfeedback', $qa, $preferredbehaviour);
//        return question_engine::make_behaviour('immediatefeedback', $qa, $preferredbehaviour);
//        //return question_engine::make_archetypal_behaviour($preferredbehaviour, $qa);
//    }

    public function loadEnv() {
        if($this->env != null) {
            return $this->env;
        }

        $this->env = $this->qtype->get_env($this->autocorrectenv);
        $this->env->setEnvOptions(json_decode($this->envoptions, true));
        return $this->env;
    }

    /**
     * Grades code if not already graded, and returns the resulting GradedCode
     *
     * @param string $runid The raw runid param from the answer
     * @param array|null $response
     * @throws moodle_exception
     * @return GradedCode
     */
    public function getGraded($runid, $response = null) {
        $this->loadEnv();
        /** @var env $env */
        $env = $this->env;

        $id = GradedCode::validateRunID($runid, $env->getSecret());
        if($id === false) {
            //$id = $this->createRun();
            throw new moodle_exception("Invalid run id!");
        }

        $graded = new GradedCode($id);

        if(/*!$graded->runid &&*/ $response) {
            $this->grade($response, $graded);
        }

        return $graded;
    }

    public function grade(array $response, &$graded = null) {
        global $COURSE;
        if($this->graded) {
            return $this->graded;
        }
        $this->loadEnv();
        /** @var env $env */
        $env = $this->env;
        if($env == null) {
            debugging('Invalid environment');
            return false;
        }

        if(!array_key_exists("runid", $response)) {
            return false;
        }

        $graded = $this->getGraded($response['runid']);

        $coursecontext = context_course::instance($COURSE->id);
        $output = $env->grade($response, $coursecontext);
        $this->graded = $output;

        $graded->setOutput($output);

        return $output;
    }



    public function grade_response(array $response) {
        $output = $this->grade($response);
        if($output == false || !$output['success']) {
            return array(0, question_state::graded_state_for_fraction(0));
        }
        $tagged = $output['tags'];
        if($tagged == false) {
            $fraction = 0;
        } else {
            if(array_key_exists("score", $tagged)) {
                $fraction = $tagged['score'];
            } else {
                $fraction = 0;
            }
        }
        $fraction = floatval($fraction);
        return array($fraction, question_state::graded_state_for_fraction($fraction));
    }

    public function createRun() {
        $id = GradedCode::createRun();
        $this->loadEnv();
        return GradedCode::getRunID($id, $this->env->getSecret());
    }

    public function get_feedback(question_attempt $qa) {
        $feedback = "";

        try {
            $graded = $this->getGraded($qa->get_last_qt_var("runid"));
        } catch (Exception $err) {
            return get_string('noattempt', 'qtype_code');
        }

        $score = $graded->output["tags"] ? $graded->output["tags"]->score * 100 : 0;
        if($score == 100) {
            $img = new moodle_url("/question/type/code/pix/check.png");
        } else if($score == 0) {
            $img = new moodle_url("/question/type/code/pix/wrong.png");
        } else {
            $img = new moodle_url("/question/type/code/pix/slash.png");
        }
        $feedback .= "<div style='text-align: center; float: left; margin-right: 20px; margin-left: 20px; margin-bottom: 20px'><img src = '$img' width='100px' height='100px'/></div>";
        $feedback .= "<h3>". get_string('yourscore', 'qtype_code', $score)."</h3><br>";

        if(!$graded->output) {
            return get_string('nooutput', 'qtype_code');
        }

        if(!$graded->output["success"]) {
            $feedback .= get_string('runerror', 'qtype_code');
        } else {
            $feedback .= get_string('runsuccess', 'qtype_code');
        }

        $feedback .= "<hr style='clear: both'>";

        if(array_key_exists("feedback", $graded->output["tags"])) {
            $feedback .= "<pre style='padding: 10px'>". $graded->output["tags"]->feedback ."</pre>";
        }

        $o = $graded->output["output"];

        if(@$o->feedback) {
            $feedback .= "<pre>". implode("\n", $o->feedback) ."</pre>";
        }

        return $feedback;
    }

    /**
     * Used by many of the behaviours, to work out whether the student's
     * response to the question is complete. That is, whether the question attempt
     * should move to the COMPLETE or INCOMPLETE state.
     *
     * @param array $response responses, as returned by
     *      {@link question_attempt_step::get_qt_data()}.
     * @return bool whether this response is a complete answer to this question.
     */
    public function is_complete_response(array $response) {
        //return $graded->output && $graded->output['success'];
        return true;
    }

    /**
     * Use by many of the behaviours to determine whether the student's
     * response has changed. This is normally used to determine that a new set
     * of responses can safely be discarded.
     *
     * @param array $prevresponse the responses previously recorded for this question,
     *      as returned by {@link question_attempt_step::get_qt_data()}
     * @param array $newresponse the new responses, in the same format.
     * @return bool whether the two sets of responses are the same - that is
     *      whether the new set of responses can safely be discarded.
     */
    public function is_same_response(array $prevresponse, array $newresponse) {
        if($prevresponse == $newresponse) {
            return true;
        }

        /*
         * This will grade the response so we can print the response
         */
        try {
            $graded = $this->getGraded($newresponse['runid'], $newresponse);
        } catch(Exception $e) {

        }
        return false;
    }

    /**
     * Produce a plain text summary of a response.
     * @param $response a response, as might be passed to {@link grade_response()}.
     * @return string a plain text summary of that response, that could be used in reports.
     */
    public function summarise_response(array $response) {
        return "Cannot get summaries for code questions";
    }

    /**
     * Categorise the student's response according to the categories defined by
     * get_possible_responses.
     * @param $response a response, as might be passed to {@link grade_response()}.
     * @return array subpartid => {@link question_classified_response} objects.
     *      returns an empty array if no analysis is possible.
     */
    public function classify_response(array $response) {
        return [];
    }

    /**
     * Use by many of the behaviours to determine whether the student
     * has provided enough of an answer for the question to be graded automatically,
     * or whether it must be considered aborted.
     *
     * @param array $response responses, as returned by
     *      {@link question_attempt_step::get_qt_data()}.
     * @return bool whether this response can be graded.
     */
    public function is_gradable_response(array $response) {
        //$graded = $this->getGraded($response['runid'], $response);
        //return $graded->output && $graded->output['success'];
        return true;
    }

    /**
     * In situations where is_gradable_response() returns false, this method
     * should generate a description of what the problem is.
     * @return string the message.
     */
    public function get_validation_error(array $response) {
        $graded = $this->getGraded($response['runid'], $response);
        return $graded->output['output']['feedback'];
    }

    /**
     * Get one of the question hints. The question_attempt is passed in case
     * the question type wants to do something complex. For example, the
     * multiple choice with multiple responses question type will turn off most
     * of the hint options if the student has selected too many opitions.
     * @param int $hintnumber Which hint to display. Indexed starting from 0
     * @param question_attempt $qa The question_attempt.
     */
    public function get_hint($hintnumber, question_attempt $qa) {

    }

    /**
     * Generate a brief, plain-text, summary of the correct answer to this question.
     * This is used by various reports, and can also be useful when testing.
     * This method will return null if such a summary is not possible, or
     * inappropriate.
     * @return string|null a plain text summary of the right answer to this question.
     */
    public function get_right_answer_summary() {
        return null;
    }

    /**
     * What data may be included in the form submission when a student submits
     * this question in its current state?
     *
     * This information is used in calls to optional_param. The parameter name
     * has {@link question_attempt::get_field_prefix()} automatically prepended.
     *
     * @return array|string variable name => PARAM_... constant, or, as a special case
     *      that should only be used in unavoidable, the constant question_attempt::USE_RAW_DATA
     *      meaning take all the raw submitted data belonging to this question.
     */
    public function get_expected_data() {
        $this->loadEnv();
        /** @var env $env */
        $env = $this->env;
        $inputs = $env->getInputs();

        $expecteddata = array();

        foreach ($inputs as $k=>$v) {
            $expecteddata[$k] = PARAM_RAW;
        }

        $expecteddata["runid"] = PARAM_RAW;

        return $expecteddata;
    }

    /**
     * What data would need to be submitted to get this question correct.
     * If there is more than one correct answer, this method should just
     * return one possibility. If it is not possible to compute a correct
     * response, this method should return null.
     *
     * @return array|null parameter name => value.
     */
    public function get_correct_response() {
        return null;
    }
}
