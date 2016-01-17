<?php

//require_once("../../../../lib/classes/event/base.php");

namespace qtype_code\event;

defined('MOODLE_INTERNAL') || die();

class code_run extends \core\event\base {

    /**
     * Override in subclass.
     *
     * Set all required data properties:
     *  1/ crud - letter [crud]
     *  2/ edulevel - using a constant self::LEVEL_*.
     *  3/ objecttable - name of database table if objectid specified
     *
     * Optionally it can set:
     * a/ fixed system context
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'c'; // c(reate), r(ead), u(pdate), d(elete)
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        //$this->data['objecttable'] = 'qtype_code_coderuns';
    }

    public static function get_name() {
        return get_string('eventcoderun', 'qtype_code');
    }

    public function get_description() {
        $env = array_key_exists("env", $this->other) ? $this->other["env"] : "unknown";
        return "Execute source code on environment \"$env\" with Run ID: ".$this->other["runid"];
    }

    public function get_url() {
        return new \moodle_url('/question/type/code/viewlog.php', array('runid' => $this->other["runid"], 'contextid' => $this->contextid));
    }

}