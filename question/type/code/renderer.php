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
 * code question renderer class.
 *
 * @package    qtype
 * @subpackage code
 * @copyright  2015 João Pedro Finoto (joao.finoto.martins@usp.br)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

function dump($var) {
    echo "<pre>";
    var_dump($var);
    echo "</pre>";
}

/**
 * Generates the output for code questions.
 *
 * @copyright  2015 João Pedro Finoto (joao.finoto.martins@usp.br)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_code_renderer extends qtype_renderer {

    private function createEditor($outName, $answer, $lang = "c_cpp", $readonly = false, $k, $filename) {
        $pxperline = 15;
        $responselines = 30;
        $uid = uniqid();
        $editor = html_writer::tag('div', $answer, array(
            "id" => "answereditor$uid",
            "style" => "height: " . ($responselines * $pxperline) . "px"
        ));
        $editor .= html_writer::tag('textarea', '', array(
            "name" => $outName,
            "style" => "display:none",
            "class" => "moodlecode_field_input",
            "data-field" => $k,
            "data-editorid" => $uid,
            "data-filename" => $filename,
            "id" => "answereditorta$uid"
        ));

        $editor .= html_writer::start_tag('script', array('type' => 'text/javascript'));
        $editor .= <<<EOF
        var editor$uid = ace.edit("answereditor$uid");
        var textarea$uid = $('#answereditorta$uid');
        editor$uid.setTheme("ace/theme/chrome");
        editor$uid.getSession().setMode("ace/mode/$lang");
        editor$uid.setOptions({
            enableBasicAutocompletion: true
        });
        textarea$uid.val(btoa(editor$uid.getSession().getValue()));
        editor$uid.getSession().on("change", function() {
            /*textarea$uid.val(btoa(encodeURIComponent(editor$uid.getSession().getValue()).replace(/%([0-9A-F]{2})/g, function(match, p1) {
                return String.fromCharCode('0x' + p1);
            })));*/
            textarea$uid.val(Base64.encode(editor$uid.getSession().getValue()))
        });
        textarea$uid.val(Base64.encode(editor$uid.getSession().getValue()));
EOF;
        if($readonly) {
            $editor .= "\neditor$uid.setReadOnly(true)";
        }
        $editor .= html_writer::end_tag('script');

        //echo "<pre>".htmlentities($editor)."</pre>";

        return $editor;
    }

    public function createText($outName, $value, $label, $readOnly = false) {
        $id = "mclfield_".uniqid();

        $text = html_writer::start_tag('p');

        $text .= html_writer::tag('label', $label, [
            "for"> $id
        ]);
        if($readOnly) {
            $text .= html_writer::tag('input', '', [
                "value" => s($value),
                "id" => $id,
                "name" => $outName,
                "disabled" => "disabled"
            ]);
        } else {
            $text .= html_writer::tag('input', '', [
                "value" => s($value),
                "id" => $id,
                "name" => $outName,
            ]);
        }

        $text .= html_writer::end_tag('p');

        return $text;
    }

    public function formulation_and_controls(question_attempt $qa,
                                             question_display_options $options) {

        /** @var qtype_code_question $question */
        $question = $qa->get_question();
        $question->loadEnv();
        $inputs = $question->env->getInputs();


        $lastVar = array_keys($inputs)[0];

        $step = $qa->get_last_step_with_qt_var($lastVar);

        if (!$step->has_qt_var($lastVar)) {
            // Question has never been answered, fill it with response template.
            $template = [];
            foreach ($inputs as $k => $v) {
                $template[$k] = $v['default'];
            }
            $template["runid"] = $question->createRun();
            $step = new question_attempt_step($template);
        }

        $questiontext = $question->format_questiontext($qa);


        $files = "";


        $result = html_writer::tag('div', $questiontext, array('class' => 'qtext'));
        $result .= html_writer::tag('input', "", array(
            'type' => 'hidden',
            "name" => $qa->get_qt_field_name("runid"),
            "value" => $step->get_qt_var("runid")
        ));
        $result .= html_writer::start_tag('div', array('class' => 'ablock'));

        $result .= html_writer::tag('div', 'error', array('class' => 'moodlecode_error',
            'class' => 'ui-state-error ui-corner-all',
            'style' => 'padding: 10px; margin-bottom: 20px; display: none'
        ));

        $result .= html_writer::tag('script', '', array(
            'src' => new moodle_url('/question/type/code/scripts/base64.js'),
            'type' => 'text/javascript'
        ));

        $result .= html_writer::tag('script', '', array(
            'src' => new moodle_url('/question/type/code/scripts/ace/ace.js'),
            'type' => 'text/javascript'
        ));

        $result .= html_writer::tag('script', '', array(
            'src' => new moodle_url('/question/type/code/scripts/ace/ext-language_tools.js'),
            'type' => 'text/javascript'
        ));

        $result .= html_writer::tag('script', '', array(
            'src' => new moodle_url('/question/type/code/scripts/jquery-2.1.4.min.js'),
            'type' => 'text/javascript'
        ));

        $result .= html_writer::tag('link', '', array(
            'href' => new moodle_url('/question/type/code/scripts/jquery-ui-1.11.4.custom/jquery-ui.min.css'),
            'rel' => 'stylesheet',
            'type' => 'text/css'
        ));

        $result .= html_writer::tag('script', '', array(
            'src' => new moodle_url('/question/type/code/scripts/jquery-ui-1.11.4.custom/jquery-ui.min.js'),
            'type' => 'text/javascript'
        ));

        $result .= html_writer::start_tag('script', array('type' => 'text/javascript'));
        $result .= <<<EOF
        ace.require("ace/ext/language_tools");
EOF;
        $result .= html_writer::end_tag('script');


        $result .= html_writer::tag('script', '', array(
            'src' => new moodle_url('/question/type/code/scripts/ide.js'),
            'type' => 'text/javascript'
        ));
        if(!$options->readonly) {
            $result .= html_writer::tag('button', 'Run', array('class' => 'moodlecode_btn_run'));
        }
        $result .= html_writer::start_tag('div', array('class' => 'moodlecode_editortabs'));
        $result .= html_writer::empty_tag('input', array(
            'type' => 'hidden',
            'class' => "moodlecode_field_input",
            'data-field' => "questionid",
            'value' => $question->id
        ));
        $result .= html_writer::empty_tag('input', array(
            'type' => 'hidden',
            'class' => 'moodlecode_url',
            'value' => base64_encode(new moodle_url('/question/type/code/api/run.php'))
        ));
        $editors_blocks = "";
        $editors_nav = html_writer::start_tag('ul', array('style' => 'height: 38px'));
        foreach ($inputs as $k=>$v) {
            if($v['type'] == 'editor') {
//                if(array_key_exists("name", $v)) {
//                    $result .= html_writer::start_tag('p');
//                    $result .= get_string('displayfilewillbe', 'qtype_code', $v['name']);
//                    $result .= html_writer::end_tag('p');
//                }
                $editors_blocks .= html_writer::start_tag('div', array('id' => $k));
                $editors_blocks .= $this->createEditor($qa->get_qt_field_name($k), s(base64_decode($step->get_qt_var($k))), $v['lang'], $options->readonly, $k, $v['name']);
                $editors_blocks .= html_writer::end_tag('div');
                $editors_nav .= html_writer::tag('li', "<a href = '#$k'>{$v['name']}</a>");
                //$result .= "<br>";
            }
            if($v['type'] == 'text') {
                $result .= $this->createText($qa->get_qt_field_name($k), $step->get_qt_var($k), $v['label']);
            }
        }
        $editors_nav .= html_writer::end_tag('ul');

        $result .= $editors_nav;
        $result .= $editors_blocks;

        $result .= html_writer::end_tag('div');

        $result .= html_writer::tag('div',$files, array('class' => 'attachments'));

        $runResults = "Press run to execute your code";
        try {
            $graded = $question->getGraded($step->get_qt_var('runid'));
            if($graded->output && $graded->output["output"]->feedback) {
                $runResults = implode("\n", $graded->output["output"]->feedback);
            }
        } catch(Exception $e) {

        }
        if(!$options->readonly) {
            $result .= html_writer::tag('pre', $runResults, array('class' => 'moodlecode_results', 'style' => 'background-color: #000; color: #FFF'));
        }
        $result .= html_writer::end_tag('div');


        return $result;
    }

    public function specific_feedback(question_attempt $qa) {
        /** @var qtype_code $question */
        $question = $qa->get_question();

        $feedback = $question->get_feedback($qa);

        return $question->format_text($feedback, FORMAT_HTML, $qa, 'question', 'answerfeedback', "");
    }

    public function correct_response(question_attempt $qa) {
        // TODO.
        return '';
    }
}
