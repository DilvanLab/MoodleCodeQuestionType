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
 * Defines the editing form for the code question type.
 *
 * @package    qtype
 * @subpackage code
 * @copyright  2015 João Pedro Finoto (joao.finoto.martins@usp.br)

 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * code question editing form definition.
 *
 * @copyright  2015 João Pedro Finoto (joao.finoto.martins@usp.br)

 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_code_edit_form extends question_edit_form {

    protected function definition_inner($mform) {
        $qtype = question_bank::get_qtype('code');

        $cid = required_param('courseid', PARAM_INT);

        $mform->addElement('header', 'autocorrecttitle', get_string('autocorrecttitle', 'qtype_code'));
        $mform->setExpanded('autocorrecttitle');

        $envsNames = $qtype->get_envs_name();
        $envs = $qtype->get_envs();
        $mform->addElement('select', 'autocorrectenv',
            get_string('autocorrectenv', 'qtype_code'), $envsNames, 'id="envselect"');
        $mform->addElement('button', 'editenv', get_string('editenv', 'qtype_code'), 'id="editenv"');
        $mform->addElement('button', 'envutils', get_string('envutils', 'qtype_code'), 'id="envutils"');
        $mform->addElement('textarea', 'envoptions',
            get_string('envoptions', 'qtype_code'), 'style="width:100%" rows="10" id="envoptions"');
        $mform->addElement('html', "<div id = 'envoptionsedit' style='margin-top: 20px; margin-bottom: 20px'></div>");
        $mform->addElement('html', "<div style='width: 100%; height: 300px' id='envoptionsace'></div>");

        $jqueryURL = new moodle_url('/question/type/code/scripts/jquery-2.1.4.min.js');
        $uiURL = new moodle_url('/question/type/code/scripts/jquery-ui-1.11.4.custom/jquery-ui.min.js');
        $uiCSSURL = new moodle_url('/question/type/code/scripts/jquery-ui-1.11.4.custom/jquery-ui.min.css');
        $aceURL = new moodle_url('/question/type/code/scripts/ace/ace.js');
        $scriptURL = new moodle_url('/question/type/code/scripts/form_editor.js');
        $ajaxURL = new moodle_url('/question/type/code/getenv.php');
        $ajaxURL->param('courseid', $cid);

        $mform->addElement('html', "<script type='text/javascript' src='$jqueryURL'></script>");
        $mform->addElement('html', "<script type='text/javascript' src='$uiURL'></script>");
        $mform->addElement('html', "<link rel='stylesheet' href='$uiCSSURL'>");
        $mform->addElement('html', "<script type='text/javascript' src='$aceURL'></script>");
        $mform->addElement('html', "<script type='text/javascript' src='$scriptURL'></script>");
        $mform->addElement('html', "<script type='text/javascript'>var ajaxURL = '$ajaxURL'</script>");

        $mform->setDefault('envoptions', '');
    }

    protected function data_preprocessing($question) {
        return $question;
    }

    public function qtype() {
        return 'code';
    }
}
