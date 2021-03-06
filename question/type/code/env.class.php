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

    public static function debug($var) {
        if(!self::$debug) {
            return;
        }
        echo "<pre>";
        var_dump($var);
        echo "</pre>";
    }

    public static function debugt($var) {
        if(!self::$debug) {
            return;
        }
        $trace = debug_backtrace();
        $t = "";
        foreach($trace as $v) {
            $t.= "\n{$v['function']}:{$v['line']}";
        }
        echo "<pre>[ENV] $var $t</pre>";
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

        $this->functions = [
            "resultCompare" => "resultCompare",
            "regexCompare" => "regexCompare"
        ];

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

        //self::debug($this->values);

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
            $output = $this->runDocker($action, $config);
        }

        //self::debug($output);

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
     * @param string $type "grade" or "test"
     * @return array
     */
    private function runDocker($do, $type) {
        //self::debug($do);
        $docker = new Docker($do['image']);
        $docker->start();

        $ret = [
            "log" => [],
            "output" => [],
            "feedback" => [],
            "code" => null,
            "success" => true
        ];

        $testcases = $this->getValue("@testcases");
        self::debugt("Run mode: $type");
        if($type != "grade") {
            $testcases = false;
            self::debugt("Ignore test cases - test run");
        }
        if($testcases && $do['testCases']) {
            $grades = [];
            for($i = 0; $i < $testcases; $i++) {
                $retk = $this->dockerGetResults($docker, $do, "@$i");
                $retk['feedback'] = array_merge($ret['feedback'], $retk['feedback']);
                $retk['log'] = array_merge($ret['log'], $retk['log']);
                $ret = $retk;
                $tags = $this->getTags($ret["output"]);
                if(array_key_exists("score", $tags)) {
                    array_push($grades, [
                        "score" => $tags['score'],
                        "weight" => $this->getValue("weight.value", "@$i")
                    ]);
                }
                if(array_key_exists("feedback", $tags) && strlen($tags['feedback']) > 0) {
                    $ret['feedback'][] = $tags['feedback'];
                }
            }


            if($do['testCases']['method']) {
                $method = $this->parse($do['testCases']['method']);
            } else {
                $method = $this->getValue("testCasesMethod.value");
            }
            self::debugt("Method: $method");
            switch($method) {
                case "lowest":
                    if(count($grades) == 0) {
                        $ret['output'] = ["score: 0"];
                    } else {
                        $min = $grades[0]["score"];
                        foreach($grades as $v) {
                            if($v['score'] < $min) {
                                $min = $v['score'];
                            }
                        }
                        $ret['output'] = ["score: ".$min];
                    }
                    break;
                case "highest":
                    if(count($grades) == 0) {
                        $ret['output'] = ["score: 0"];
                    } else {
                        $max = $grades[0]["score"];
                        foreach($grades as $v) {
                            if($v['score'] > $max) {
                                $max = $v['score'];
                            }
                        }
                        $ret['output'] = ["score: ".$max];
                    }
                    break;
                case "weighted":
                    if(count($grades) == 0) {
                        $ret['output'] = ["score: 0"];
                    } else {
                        $weights = 0;
                        $val = 0;
                        foreach($grades as $v) {
                            $weights += $v['weight'];
                        }

                        self::debugt("Total weights: $weights");

                        foreach($grades as $v) {
                            $val += $v['score'] * $v['weight'] / $weights;
                        }

                        $ret['output'] = ["score: ".$val];
                    }
                    break;
                default:
                    $ret['output'] = ["score: 0", "feedback: Invalid test case method: ".$method];
                    break;
            }

        } else {
            $ret = $this->dockerGetResults($docker, $do);
        }

        $docker->stop();

        return $ret;
    }

    function processIf($if, $do, $prefix) {
        $a = $this->parse($if['a'], $prefix);
        $b = $this->parse($if['b'], $prefix);

        self::debugt("Compare: {$if['a']} and {$if['b']} \n $a {$if['operation']} $b");

        switch($if['operation']) {
            case '=':
                return $a == $b;
            case '!=':
                return $a != $b;
            default:
                return false;
        }
    }

    /**
     * Runs the action and returns the results, already processed.
     * Changes this->_output
     * @param Docker $docker
     * @param array $do the action object
     * @param string $prefix prefix added to the parser
     * @return array
     */
    function dockerGetResults($docker, $do, $prefix = "") {
        $this->createFiles($prefix);

        if(is_array($do['copy'])) {
            $docker->exec("rm -rf ".$do['copyTo']);
            $docker->exec("bash -c \"ls {$do['copyTo']}\"");
            // create remote directory
            $docker->exec("mkdir -p ".$do['copyTo']);

            // copy only certain files
            foreach ($do['copy'] as $v) {
                $docker->send($this->tmp."/".$v, $do['copyTo']);
            }
        } else if($do['copy'] == '*'){
            $docker->exec("rm -rf ".$do['copyTo']);
            $docker->exec("bash -c \"ls {$do['copyTo']}\"");
            // copy everything
            $docker->send($this->tmp, $do['copyTo']);
        }

        // check environment
        $docker->exec("bash -c \"ls {$do['copyTo']}\"");

        $reti = [
            "log" => [],
            "output" => [],
            "feedback" => [],
            "code" => null,
            "success" => true
        ];
        foreach ($do['commands'] as $v) {
            $val = 0;
            $cmd = false;
            //self::debug($v);
            if(!is_array($v)) {
                // single commands replace current output
                //self::debugt("Single command");
                unset($reti['output']);
                $cmd = $v;
                $docker->exec('bash -c "cd '.$do['copyTo'].'; '.$this->parse($cmd, $prefix).'"', $reti['output'], $val);
                $reti["code"] = $val;
            } else {
                // this command has special options
                self::debugt($v['cmd']);
                $output = false;
                if(array_key_exists("cmd", $v)) {
                    $cmd = $v['cmd'];
                }
                if($cmd === false) {
                    continue;
                }
                if(array_key_exists("if:and", $v)) {
                    self::debugt("process IF");
                    if(is_array($v['if:and'])) {
                        $success = true;
                        foreach($v['if:and'] as $u) {
                            if(!$this->processIf($u, $do, $prefix)) {
                                $success = false;
                                self::debugt("Failed");
                                break;
                            }
                        }
                        if(!$success) {
                            continue;
                        }
                    } else {
                        if(!$this->processIf($v['if:and'], $do, $prefix)) {
                            continue;
                        }
                    }
                }
                self::debugt("Run");
                if(array_key_exists("output", $v)) {
                    if(is_array($v['output'])) {
                        $output = $v['output'];
                    } else {
                        $output = [$v['output']];
                    }
                }
                $result = [];
                $docker->exec('bash -c "cd '.$do['copyTo'].'; '.$this->parse($cmd, $prefix).'"', $result, $val);
                $reti["code"] = $val;

                foreach($output as $o) {
                    $reti[$o] = array_merge($reti[$o], $result);
                }
            }


            if($val) {
                // return code non-zero means something went wrong
                // mark this as a failure and stop
                //self::debugt("Non-zero return value: $val - abort");
                $reti['success'] = false;
                $reti['feedback'][] = "Non-zero return value: $val - Execution stopped \nCommand: $cmd";
                break;
            }
        }

        $this->setValue("_output", $reti);

        if(isset($do['outputProcess'])) {
            $params = [];
            foreach($do['outputProcess']['params'] as $k => $v) {
                $params[$k] = $this->getValue($v, $prefix);
            }

            if(isset($this->functions[$do['outputProcess']['function']])) {
                $reti['output'] = call_user_func($this->functions[$do['outputProcess']['function']], $params, $this);
            }
        }

        return $reti;
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
    private function createFiles($prefix = "") {
        //self::debugt($prefix);
        if($prefix) {
            $u = $this->values[$prefix];
        } else {
            $u = $this->values;
        }
        //self::debug($u);
        foreach ($u as $k => $v) {
            $v = $this->getValue($k, $prefix);
            //self::debug($v);
            if(is_array($v) && array_key_exists("output", $v) && @!$v['output']['nofile']) {
                if(@$v['output']['type'] == 'file' || @$v['output']['type'] == 'std') {
                    if(!array_key_exists('value', $v['output'])) {
                        $v['output']['value'] = null;
                    }
                    @unlink($this->tmp."/".$v['output']['name']);
                    //self::debugt("PUT CONTENTS: ". base64_decode($v['output']['value']));
                    file_put_contents($this->tmp."/".$v['output']['name'], base64_decode($v['output']['value']));
                } else if(@$v['output']['type'] == 'json') {
                    if(!array_key_exists('value', $v['output'])) {
                        $v['output']['value'] = null;
                    }
                    @unlink($this->tmp."/".$v['output']['name']);
                    file_put_contents($this->tmp."/".$v['output']['name'], json_encode($v['output']['value']));
                }
            }
        }
    }

    /**
     * Parse commands, replacing variables with their values.
     * Variable format: %field.(...).value.
     * Values are acquired using getValue()
     * @param string $cmd
     * @param string $prefix Prefix for the value
     * @return string
     */
    private function parse($cmd, $prefix = "") {
        //self::debugt("Parse: $cmd");
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
                $out .= $this->getValue($token, $prefix);
                $token = "";
                $out .= $postAppend;
            }


        }

        if($toToken) {
            $out .= $this->getValue($token, $prefix);
        }

        return $out;
    }

    /**
     * @param string $var the field name
     * @param string $prefix
     * @return array|null the value
     */
    private function getValue($var, $prefix = "") {
        $pieces = array_reverse(explode(".", $var));
        if($prefix) {
            array_push($pieces, $prefix);
        }
        $arr = $this->values;
        while (count($pieces)) {
            ////self::debug($arr);
            $v = array_pop($pieces);
            if(!array_key_exists($v, $arr)) {
                if($prefix) {
                    return $this->getValue($var);
                }
                //self::debugt("There is no $var");
                return null;
            }
            $arr = $arr[$v];
        }
        if($prefix) {
            try {
                $arr = $this->mergeOptions($arr, $this->getValue($var));
            } catch(Exception $err) {

            }
        }

        //self::debugt("$var is $arr");
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
        ////self::debug($ov);
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

            if(array_key_exists("hidden", $v)) {
                continue;
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
 * @param array $params
 * @param env $env
 * @return array
 */
function resultCompare($params, $env) {
    $correct = 0;

    $env->debug($params);

    $model = explode("\n", base64_decode($params['model']));

    $model = array_filter($model, function($var) {
        return (!preg_match('/^ *$/', $var));
    });

    $num = count($model);

    $response = $params['response'];

    $response = array_filter($response, function($var) {
        return (!preg_match('/^ *$/', $var));
    });

    $env->debug($model);
    $env->debug($response);

    foreach($model as $k => $v) {
        $vv = array_shift($response);
        if(trim($vv) == trim($v)) {
            $correct++;
        }
    }

    $num += count($response);
    if(!$num) {
        $score = 0;
    } else {
        $score = ($correct/$num);
    }

    $r = [
        "score: ". $score
    ];

    if($score != 1.0 && array_key_exists("feedback", $params)){
        $r[] = "feedback: " . str_replace("\n", "\\n ", $params['feedback']);
    }

    $env->debug($r);

    return $r;
}

/**
 * @param $params
 * @param env $env
 * @return array
 */
function regexCompare($params, $env) {
    $env->debug($params);

    $response = implode($params['response']);
    $grade = 0;
    foreach($params["model"] as $v) {
        if($v['regex'] && strlen($v['regex']) > 0) {
            $rx = "/{$v['regex']}/i";
            $env->debugt("Match $rx?");
            if(@preg_match($rx, $response)) {
                $grade = $v['fraction'];
                break;
            }
            $env->debugt("no");
        }
    }

    $r = [
        "score: ". $grade
    ];

    if($grade != 1.0 && array_key_exists("feedback", $params)){
        $r[] = "feedback: " . str_replace("\n", "\\n ", $params['feedback']);
    }

    return $r;
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