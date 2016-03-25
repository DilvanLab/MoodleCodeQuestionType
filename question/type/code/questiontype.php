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
 * Question type class for the code question type.
 *
 * @package    qtype
 * @subpackage code
 * @copyright  2015 João Pedro Finoto (joao.finoto.martins@usp.br)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->dirroot . '/question/type/code/question.php');
require_once('env.class.php');


/**
 * The code question type.
 *
 * @copyright  2015 João Pedro Finoto (joao.finoto.martins@usp.br)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_code extends question_type {

    private static $workdir = "/var/moodlecode";

    public function available_languages() {
        return array("C", "C++", "JAVA", "Python");
    }

    private static $exclude_dirs = [".", ".."];

    /**
     * @return array of @link{env} objects
     */
    public function get_envs() {
        $dirs = scandir(self::$workdir."/env");
        $envs = [];

        foreach($dirs as $v) {
            if(array_search($v, self::$exclude_dirs) === false) {
                $dir = self::$workdir."/env/".$v;
                try {
                    $v = new env($dir);
                    $envs[] = $v;
                } catch(Exception $e) {
                    continue;
                }
            }
        }

        return $envs;
    }

    public function get_envs_name() {
        $envs = $this->get_envs();
        $er = [];

        foreach($envs as $v) {
            $er[$v->getID()] = $v->getName();
        }

        return $er;
    }

    public function get_env($id) {
        $dir = self::$workdir."/env/".$id;
        try {
            $v = new env($dir);
            return $v;
        } catch(Exception $e) {
            die($e->getMessage());
        }
    }

    public function move_files($questionid, $oldcontextid, $newcontextid) {
        parent::move_files($questionid, $oldcontextid, $newcontextid);
        $this->move_files_in_hints($questionid, $oldcontextid, $newcontextid);
    }


    protected function delete_files($questionid, $contextid) {
        parent::delete_files($questionid, $contextid);
        $this->delete_files_in_hints($questionid, $contextid);
    }

    public function get_question_options($question) {
        global $DB;
        $question->options = $DB->get_record('qtype_code_options',
            array('questionid' => $question->id), '*', MUST_EXIST);
        parent::get_question_options($question);
    }

    public function save_question_options($question) {
        //$question->responsetemplate = base64_encode($question->responsetemplate);
        parent::save_question_options($question);
    }

    protected function initialise_question_instance(question_definition $question, $questiondata) {
        // TODO.
        parent::initialise_question_instance($question, $questiondata);
    }

    public function get_random_guess_score($questiondata) {
        // TODO.
        return 0;
    }

    public function get_possible_responses($questiondata) {
        // TODO.
        return array();
    }

    public function extra_question_fields() {
        return array(
            'qtype_code_options',
            'autocorrectenv',
            'envoptions'
        );
    }
}
