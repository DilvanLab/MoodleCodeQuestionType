<?php

require_once("Docker.class.php");
require_once("classes/event/code_run.php");

class env {

    /**
     * This var should be changed!
     * @var string
     */
    private static $secret = "HDATdeRAYpR4rN6Z6yLBbbk3NkoH7079KYQTLKkDtNCbgnQwmL40AnT1KP4W4GES";
    private $id;
    private $options;
    private $dir;
    private $values;
    private $tmp;
    private static $debug = false;

    const OUTPUT_LOG = "log";
    const OUTPUT_OUTPUT = "output";
    const OUTPUT_FEEDBACK = "feedback";

    private static function debug($var) {
        if(!self::$debug) {
            return;
        }
        echo "<pre>";
        var_dump($var);
        echo "</pre>";
    }

    private static function debugt($var) {
        if(!self::$debug) {
            return;
        }
        echo "<pre>[ENV] $var</pre>";
    }

    /**
     * @param string $dir path to environment
     * @throws InvalidArgumentException if $dir is not a valid environment
     * @throws Exception if $dir/env.json doesn't exist or is invalid
     */
    public function __construct($dir) {
        // remove trailing /, if it exists
        $dir = rtrim($dir, '/');

        if(!is_dir($dir)) {
            throw new InvalidArgumentException("$dir is not a directory");
        }

        $this->dir = $dir;

        $dirExplode = explode("/", $dir);
        $this->id = $dirExplode[count($dirExplode) - 1];

        $files = scandir($dir);
        if(array_search("env.json", $files) === false) {
            throw new Exception("Missing env.json");
        }

        $envtext = file_get_contents($dir."/env.json");
        if($envtext === false ) {
            throw new Exception("Cannot read env.json");
        }

        $options = json_decode($envtext, true);
        if($options === null){
            throw new Exception("env.json is invalid");
        }

        if(!isset($options["name"]) || $options["name"] == "") {
            $options["name"] = $this->id;
        }

        $this->options = $options;
        $this->setEnvOptions();
    }

    /**
     * Returns this environment's secret for HMAC encryption
     * of the run ID
     *
     * @return string the env secret
     */
    public function getSecret() {
        if(array_key_exists("secret", $this->options)) {
            return $this->options["secret"];
        } else {
            return self::$secret;
        }
    }

    public function getOptions() {
        return $this->options;
    }

    /**
     * Parses $answers and executes all commands.
     * Return format:
     * runid: unique id for this run
     * output: raw outputs
     * tags: tagged output
     *
     * @param array $answers
     * @param object $context the context used for logging
     * @param string $config the type of run
     * @throws coding_exception
     * @throws file_exception
     * @throws stored_file_creation_exception
     * @return array|false The tagged output of the grader, false if failed to run
     */
    public function grade(array $answers, $context = null, $config = "grade") {
        // create temporary environment
        $this->createTmp();

        // load answer values
        $this->parseAnswers($answers);

        // create temporary files
        $this->createFiles();

        self::debug($this->values);

        $runid = time()."-".sha1(microtime().uniqid().rand());
        $output = false;
        if(is_array($this->options["action"]) && array_key_exists($config, $this->options["action"])) {
            $action = $this->options["action"][$config];
        } else {
            $action = $this->options["action"];
        }
        if(is_string($action)) {
            $output = $this->run($this->parse($action));
        } else if($action["type"] == "docker"){
            $output = $this->runDocker($action);
        }

        self::debug($output);

        if($context) {
            // log and save run to file
            $fs = get_file_storage();
            $outputNames = ["log", "output", "feedback"];

            foreach($outputNames as $v) {
                // Prepare file record object
                $fileinfo = array(
                    'contextid' => $context->id,    // ID of context
                    'component' => 'qcode',         // usually = table name
                    'filearea' => 'runs',           // usually = table name
                    'itemid' => 0,                  // usually = ID of row in table
                    'filepath' => '/',              // any path beginning and ending in /
                    'filename' => "$runid-$v.txt"); // any filename

                $file = $fs->create_file_from_string($fileinfo, implode("\n", $output[$v]));
            }

            // trigger the event
            global $USER;

            $event = \qtype_code\event\code_run::create([
                "other" => [
                    "runid" => $runid,
                    "env" => $this->options['name']
                ],
                "relateduserid" => $USER->id,
                "contextid" => $context->id
            ]);

            $event->trigger();
        }

        $tags = $this->getTags($output['output']);

        return [
            "runid" => $runid,
            "tags" => $tags,
            "output" => $output,
            "success" => $output['success']
        ];
    }

    /**
     * Runs the command and return an array containing each line
     * of the output. The last value of this array will be the
     * exit code for the program.
     * The return array will have the following keys:
     * output: array of strings, the output of the command
     * code: the exit code for the last executed process
     * log: log info, used in the run log
     * feedback: used for student feedback (for instance, compiling errors)
     * success: bool
     *
     * @param $str
     * @return array
     */
    private function run($str) {
        $ret = [];
        $output = [];
        $exit = 0;
        exec($str, $output, $exit);
        $ret["output"] = $output;
        $ret["code"] = $exit;
        $ret["log"] = $output;
        $ret["feedback"] = $output;
        $ret["success"] = ($exit == 0);
        return $ret;
    }

    /**
     * Creates a Docker container, copies files and runs commands
     * specified in $options['action'].
     * Returns in the same way as run()
     *
     * @param array $do the action to run
     * @return array
     */
    private function runDocker($do) {
        $docker = new Docker($do['image']);
        $docker->start();

        if(is_array($do['copy'])) {
            // create remote directory
            $docker->exec("mkdir -p ".$do['copyTo']);

            // copy only certain files
            foreach ($do['copy'] as $v) {
                $docker->send($this->tmp."/".$v, $do['copyTo']);
            }
        } else if($do['copy'] == '*'){
            // copy everything
            $docker->send($this->tmp, $do['copyTo']);
        }

        // check environment
        $docker->exec("bash -c \"ls {$do['copyTo']}\"");

        $ret = [
            "log" => [],
            "output" => [],
            "feedback" => [],
            "code" => null,
            "success" => true
        ];
        foreach ($do['commands'] as $v) {
            $val = 0;
            self::debug($v);
            if(!is_array($v)) {
                // single commands replace current output
                self::debugt("Single command");
                unset($ret['output']);
                $cmd = $v;
                $docker->exec('bash -c "cd '.$do['copyTo'].'; '.$this->parse($cmd).'"', $ret['output'], $val);
                $ret["code"] = $val;
            } else {
                // this command has special options
                self::debugt("Full command");
                $cmd = false;
                $output = false;
                if(array_key_exists("cmd", $v)) {
                    $cmd = $v['cmd'];
                }
                if($cmd === false) {
                    continue;
                }
                if(array_key_exists("output", $v)) {
                    if(is_array($v['output'])) {
                        $output = $v['output'];
                    } else {
                        $output = [$v['output']];
                    }
                }
                $result = [];
                $docker->exec('bash -c "cd '.$do['copyTo'].'; '.$this->parse($cmd).'"', $result, $val);
                $ret["code"] = $val;

                foreach($output as $o) {
                    $ret[$o] = array_merge($ret[$o], $result);
                }
            }


            if($val) {
                // return code non-zero means something went wrong
                // mark this as a failure and stop
                self::debugt("Non-zero return value: $val - abort");
                $ret['success'] = false;
                break;
            }
        }

        $docker->stop();

        return $ret;
    }

    /**
     * This method will transform
     * $answers[i] into $this->values[id]['output']['value'].
     *
     * @param array $answers
     */
    private function parseAnswers(array $answers) {
        $inputs = $this->getInputs();
        foreach ($inputs as $k => $v) {
            $value = null;
            if(array_key_exists($k, $answers)) {
                $value = $answers[$k];
            }

            $this->setValue($v['id'], $value);
        }
    }

    /**
     * Creates any temporary files (when output.type == "file")
     * Files will output to $this->tmp
     */
    private function createFiles() {
        foreach ($this->values as $k => $v) {
            if(is_array($v) && array_key_exists("output", $v)) {
                if($v['output']['type'] == 'file') {
                    if(!array_key_exists('value', $v['output'])) {
                        $v['output']['value'] = null;
                    }
                    file_put_contents($this->tmp."/".$v['output']['name'], base64_decode($v['output']['value']));
                }
            }
        }
    }

    /**
     * Parse commands, replacing variables with their values.
     * Variable format: %field.(...).value.
     * Values are acquired using getValue()
     * @param $cmd
     * @return string
     */
    private function parse($cmd) {
        self::debugt("Parse: $cmd");
        $out = "";
        $token = "";
        $toToken = false;
        $ignore = false;
        for($i = 0; $i < strlen($cmd); $i++) {
            $c = $cmd[$i];
            $parseToken = false;
            $postAppend = "";
            if($c == '\\') {
                if($ignore) {
                    if($toToken) {
                        $token .= $c;
                    } else {
                        $out .= $c;
                    }
                } else {
                    $ignore = true;
                }
            } else {
                if($c == '%') {
                    if($ignore) {
                        if($toToken) {
                            $token .= $c;
                        } else {
                            $out .= $c;
                        }
                    } else {
                        if($toToken) {
                            $parseToken = true;
                        } else {
                            $toToken = true;
                        }
                    }
                } else {
                    $ignore = false;
                    if($c == ' ') {
                        if($toToken) {
                            $parseToken = true;
                            $toToken = false;
                            $postAppend = $c;
                        } else {
                            $out .= $c;
                        }
                    } else {
                        if($toToken) {
                            $token .= $c;
                        } else {
                            $out .= $c;
                        }
                    }
                }
            }

            if($parseToken) {
                $out .= $this->getValue($token);
                $token = "";
                $out .= $postAppend;
            }


        }

        if($toToken) {
            $out .= $this->getValue($token);
        }

        return $out;
    }

    /**
     * @param string $var the field name
     * @return array|null the value
     */
    private function getValue($var) {
        $pieces = array_reverse(explode(".", $var));
        $arr = $this->values;
        while (count($pieces)) {
            $v = array_pop($pieces);
            if(!array_key_exists($v, $arr)) {
                self::debugt("There is no $var");
                return null;
            }
            $arr = $arr[$v];
        }
        self::debugt("$var is $arr");
        return $arr;

    }

    /**
     * Checks for and validates fields
     *
     * @param string $var field name
     * @param string $value field value
     * @return string the validated string
     */
    private function validate($var, $value) {
        if(!array_key_exists("validation", $this->options)) {
            return $value;
        }

        foreach ($this->options['validation'] as $v) {
            if($v['id'] != $var) {
                continue;
            }
            switch ($v['type']) {
                case 'replace':
                    $value = preg_replace($v['exp'], $v['replace'], $value);
                    break;
            }
        }

        return $value;
    }

    /**
     * Sets a value for $var
     *
     * @param string $var the name of the field
     * @param mixed $value the new value for the field
     */
    private function setValue($var, $value) {
        $value = $this->validate($var, $value);
        $pieces = explode(".", $var);
        $arr = $value;
        while (count($pieces)) {
            $v = array_pop($pieces);
            $arr = [
                $v => $arr
            ];
        }
        $this->values = $this->mergeOptions($this->values, $arr);
    }

    public function setEnvOptions(array $ov = []) {
        $optionsValue = [];

        $av = $this->options;
        if(array_key_exists("options", $av)) {
            foreach($av["options"] as $v) {
                $optionsValue[$v["id"]] = $v;
            }
        }

        if(array_key_exists("inputs", $av)) {
            foreach($av["inputs"] as $v) {
                $optionsValue[$v["id"]] = $v;
            }
        }

        $optionsValue = $this->mergeOptions($optionsValue, $ov);

        $this->values = $optionsValue;
    }

    /**
     * Merges two associative arrays. $b has priority over $a
     *
     * @param $a
     * @param $b
     * @return array
     */
    private function mergeOptions(array $a, array $b) {
        if(!is_array($a)) {
            return (is_array($b) ? $b : []);
        } else if(!is_array($b)) {
            return [];
        }
        $r = $a;
        foreach($b as $k=>$v) {
            if(array_key_exists($k, $r)) {
                if(is_array($r[$k]) && is_array($v)) {
                    $r[$k] = $this->mergeOptions($r[$k], $v);
                } else {
                    $r[$k] = $v;
                }
            } else {
                $r[$k] = $v;
            }
        }

        return $r;
    }

    private function createTmp() {
        $this->tmp = "/tmp/mcl_".uniqid();
        recursive_copy($this->dir, $this->tmp);
    }

    public function getName() {
        return $this->options["name"];
    }

    public function getID() {
        return $this->id;
    }

    public function toString() {
        return $this->getName();
    }

    /**
     * Creates a unique string representing this field
     * @param string $name
     * @return string
     */
    public function fieldname($name) {
        return preg_replace("/[^A-Za-z0-9]/", '', $name).substr(md5($name), 0, 10);
    }

    /**
     * Returns inputs for this question. Used to generate field names and expected input
     * for the question engine
     *
     * @param bool $useValues if modified values should be used
     * @param bool $fieldname if the id returned should be unique (using env::fieldname)
     * @return array of inputs
     * @throws coding_exception
     */
    public function getInputs($useValues = true, $fieldname = true) {
        $inputs = [];

        foreach ($this->options['inputs'] as $v) {
            if($useValues) {
                $v = $this->values[$v['id']];
            }

            $addName = false;
            if(is_array($v['output']) && array_key_exists('name', $v['output'])) {
                if($v['output']['name'] == "") {
                    $id = $v['id'].".output.name";
                    if($fieldname) {
                        $k = $this->fieldname($id);
                    } else {
                        $k = $id;
                    }

                    if(array_key_exists('defaultName', $v['output'])) {
                        $default = $v['output']['defaultName'];
                    } else {
                        $default = "";
                    }

                    $inputs[$k] = [
                        "type" => "text",
                        "id" => $id,
                        "label" => get_string('namethisfile', 'qtype_code'),
                        "default" => $default
                    ];
                } else {
                    $addName = true;
                }
            }

            $id = $v['id'].".output.value";
            if($fieldname) {
                $k = $this->fieldname($id);
            } else {
                $k = $id;
            }
            if(strlen($k) > 32) {
                $k = substr($k, 0, 32);
            }
            $inputs[$k] = [
                "type" => $v['type'],
                "id" => $id,
                "default" => $this->getValue($v['id'].".default")
            ];

            if($addName) {
                $inputs[$k]['name'] = $v['output']['name'];
            }
            if($v['type'] == 'editor') {
                $inputs[$k]['lang'] = isset($v['lang']) ? $v['lang'] : "";
            }
        }

        return $inputs;
    }

    private static $tags = [
        "score" => "/score:(.*)/",
        "feedback" => "/feedback:(.*)/"
    ];

    /**
     * Parses the tags from the rader output
     *
     * @param $output array of string The raw grader output
     * @return array the tags
     */
    private function getTags($output) {
        $tagged = [];
        foreach (self::$tags as $k => $v) {
            foreach ($output as $k2 => $o) {
                // stop on the first empty line
                if($o == '') {
                    break;
                }
                $matches = [];
                if(preg_match($v, $o, $matches)) {
                    // remove full string
                    unset($matches[0]);

                    $matches = array_values($matches);

                    // single values are transformed into strings
                    if(count($matches) == 1) {
                        $matches = $matches[0];
                    }

                    $tagged[$k] = $matches;

                    // remove current line to avoid multiple matches
                    unset($output[$k]);

                    break;
                }
            }
        }

        return $tagged;
    }
}

/**
 * From http://php.net/manual/en/function.copy.php#91010
 * Target dir is created
 * @param string $src source dir
 * @param string $dst target dir
 */
function recursive_copy($src,$dst) {
    $dir = opendir($src);
    @mkdir($dst);
    while(false !== ( $file = readdir($dir)) ) {
        if (( $file != '.' ) && ( $file != '..' )) {
            if ( is_dir($src . '/' . $file) ) {
                recursive_copy($src . '/' . $file,$dst . '/' . $file);
            }
            else {
                copy($src . '/' . $file,$dst . '/' . $file);
            }
        }
    }
    closedir($dir);
}