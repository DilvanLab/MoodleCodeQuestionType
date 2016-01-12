<?php

require_once("Docker.class.php");

class env {

    private $id;
    private $options;
    private $dir;
    private $values;
    private $tmp;

    private static function debug($var) {
        echo "<pre>";
        var_dump($var);
        echo "</pre>";
    }

    private static function debugt($var) {
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

    public function getOptions() {
        return $this->options;
    }

    /**
     * Parses $answers and executes all commands.
     * @param array $answers
     * @return array|false The tagged output of the grader, false if failed to run
     */
    public function grade(array $answers) {
        // create temporary environment
        $this->createTmp();

        // load answer values
        $this->parseAnswers($answers);

        // create temporary files
        $this->createFiles();

        self::debug($this->values);

        $output = false;
        if(is_string($this->options["action"])) {
            $output = $this->run($this->parse($this->options["action"]));
        } else if($this->options["action"]["type"] == "docker"){
            $output = $this->runDocker();
        }

        self::debug($output);
        if($output == false) {
            return false;
        }

        // remove exit code
        array_pop($output);

        return $this->getTags($output);
    }

    /**
     * Runs the command and return an array containing each line
     * of the output. The last value of this array will be the
     * exit code for the program.
     *
     * @param $str
     * @return array
     */
    private function run($str) {
        $ret = [];
        $exit = 0;
        exec($str, $ret, $exit);
        $ret[] = $exit;
        return $ret;
    }

    /**
     * Creates a Docker container, copies files and runs commands
     * specified in $options['action'].
     * Returns in the same way as run()
     *
     * @return array
     */
    private function runDocker() {
        $do = $this->options["action"];
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

        $ret = [];
        foreach ($do['commands'] as $v) {
            $val = 0;
            unset($ret);
            $docker->exec('bash -c "cd '.$do['copyTo'].'; '.$this->parse($v).'"', $ret, $val);
            $ret[] = $val;

            if($val) {
                self::debugt("Non-zero return value: $val - abort");
                $docker->stop();
                return false;
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
     * Merges two associative arrays. $b overlaps $a
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

    public function fieldname($name) {
        return preg_replace("/[^A-Za-z0-9]/", '', $name).substr(md5($name), 0, 10);
    }

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
        "score" => "/score:(.*)/"
    ];
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
                    self::debug($matches);
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