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

    private function createEditor($outName, $answer, $lang = "c_cpp", $readonly = false) {
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
            "id" => "answereditorta$uid"
        ));
        $editor .= html_writer::tag('script', '', array(
            'src' => new moodle_url('/question/type/code/scripts/ace/ace.js'),
            'type' => 'text/javascript'
        ));
        $editor .= html_writer::tag('script', '', array(
            'src' => new moodle_url('/question/type/code/scripts/jquery-2.1.4.min.js'),
            'type' => 'text/javascript'
        ));
        $editor .= html_writer::start_tag('script', array('type' => 'text/javascript'));
        $editor .= <<<EOF
        var editor$uid = ace.edit("answereditor$uid");
        var textarea$uid = $('#answereditorta$uid');
        editor$uid.setTheme("ace/theme/chrome");
        editor$uid.getSession().setMode("ace/mode/$lang");
        textarea$uid.val(btoa(editor$uid.getSession().getValue()));
        editor$uid.getSession().on("change", function() {
            textarea$uid.val(btoa(encodeURIComponent(editor$uid.getSession().getValue()).replace(/%([0-9A-F]{2})/g, function(match, p1) {
                return String.fromCharCode('0x' + p1);
            })));
        });
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
            $step = new question_attempt_step($template);
        }

        $questiontext = $question->format_questiontext($qa);


        $files = "";

        $result = html_writer::tag('div', $questiontext, array('class' => 'qtext'));
        $result .= html_writer::start_tag('div', array('class' => 'ablock'));

        foreach ($inputs as $k=>$v) {
            if($v['type'] == 'editor') {
                if(array_key_exists("name", $v)) {
                    $result .= html_writer::start_tag('p');
                    $result .= get_string('displayfilewillbe', 'qtype_code', $v['name']);
                    $result .= html_writer::end_tag('p');
                }
                $result .= $this->createEditor($qa->get_qt_field_name($k), s(base64_decode($step->get_qt_var($k))), $v['lang']);
                $result .= "<br>";
            }
            if($v['type'] == 'text') {
                $result .= $this->createText($qa->get_qt_field_name($k), $step->get_qt_var($k), $v['label']);
            }
        }

        $result .= html_writer::tag('div',$files, array('class' => 'attachments'));
        $result .= html_writer::end_tag('div');


        return $result;
    }

    public function specific_feedback(question_attempt $qa) {
        // TODO.
        return '';
    }

    public function correct_response(question_attempt $qa) {
        // TODO.
        return '';
    }
}
