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


/**
 * Represents a code question.
 *
 * @copyright  2015 João Pedro Finoto (joao.finoto.martins@usp.br)

 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_code_question extends question_with_responses implements question_automatically_gradable {

    public $responselang;
    public $envoptions;
    public $autocorrectenv;
    /**
     * env
     */
    public $env = null;

    public function __construct() {

    }

    public function check_file_access($qa, $options, $component, $filearea,
            $args, $forcedownload) {
        // TODO.
        if ($component == 'question' && $filearea == 'hint') {
            return $this->check_hint_file_access($qa, $options, $args);

        } else {
            return parent::check_file_access($qa, $options, $component, $filearea,
                    $args, $forcedownload);
        }
    }

    public function make_behaviour(question_attempt $qa, $preferredbehaviour) {
        return question_engine::make_behaviour('deferredfeedback', $qa, $preferredbehaviour);
    }

    public function loadEnv() {
        if($this->env != null) {
            return $this->env;
        }
        $this->env = $this->qtype->get_env($this->autocorrectenv);
        $this->env->setEnvOptions(json_decode($this->envoptions, true));
        return $this->env;
    }

    public function grade_response(array $response) {
        // TODO.
        $this->loadEnv();
        $env = $this->env;
        if($env == null) {
            debugging('Invalid environment');
            return null;
        }
        $tagged = $env->grade($response);
        if($tagged == false) {
            $fraction = 0;
        } else {
            if(array_key_exists("score", $tagged)) {
                $fraction = $tagged['score'];
            } else {
                $fraction = 0;
            }
        }
        return array($fraction, question_state::graded_state_for_fraction($fraction));
    }

    public function compute_final_grade($responses, $totaltries) {
        // TODO.
        return 0;
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
        // TODO: Implement is_same_response() method.
        return false;
    }

    /**
     * Produce a plain text summary of a response.
     * @param $response a response, as might be passed to {@link grade_response()}.
     * @return string a plain text summary of that response, that could be used in reports.
     */
    public function summarise_response(array $response) {
        // TODO: Implement summarise_response() method.
        return "";
    }

    /**
     * Categorise the student's response according to the categories defined by
     * get_possible_responses.
     * @param $response a response, as might be passed to {@link grade_response()}.
     * @return array subpartid => {@link question_classified_response} objects.
     *      returns an empty array if no analysis is possible.
     */
    public function classify_response(array $response) {
        // TODO: Implement classify_response() method.
        return "";
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
        // TODO: Implement is_gradable_response() method.
        return true;
    }

    /**
     * In situations where is_gradable_response() returns false, this method
     * should generate a description of what the problem is.
     * @return string the message.
     */
    public function get_validation_error(array $response) {
        // TODO: Implement get_validation_error() method.
        return "";
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
        // TODO: Implement get_hint() method.

    }

    /**
     * Generate a brief, plain-text, summary of the correct answer to this question.
     * This is used by various reports, and can also be useful when testing.
     * This method will return null if such a summary is not possible, or
     * inappropriate.
     * @return string|null a plain text summary of the right answer to this question.
     */
    public function get_right_answer_summary() {
        // TODO: Implement get_right_answer_summary() method.
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
        $env = $this->env;
        $inputs = $env->getInputs();

        $expecteddata = array();

        foreach ($inputs as $k=>$v) {
            $expecteddata[$k] = PARAM_RAW;
        }

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
        // TODO: Implement get_correct_response() method.
        return null;
    }
}
