<?php

class Docker {
    private $image;
    private $id;

    private static function debug($str) {
        echo "<pre>[DOCKER] $str</pre>";
    }

    public function __construct($image) {
        $this->image= $image;
    }

    public function start() {
        $this->id = trim(shell_exec("docker run -t -d $this->image"));
        self::debug("started with id $this->id");
    }

    public function exec($command, &$lines = null, &$val = null) {
        return $this->run_local("docker exec $this->id $command", $lines, $val);
    }

    public function run_local($command, &$lines = null, &$val = null) {
        self::debug("run command: $command");
        $r = exec($command, $lines, $val);
        self::debug(implode("\n[DOCKER] ", $lines));
        return $r;
    }

    public function stop() {
        self::debug("killing $this->id");
        shell_exec("docker kill $this->id");
    }

    /**
     * Copy files from a local path to the container
     * @param $local
     * @param $remote
     */
    public function send($local, $remote) {
        $this->run_local("docker cp $local $this->id:$remote 2>&1");
    }

    /**
     * Copy files from the container to a local path
     * @param $remote
     * @param $local
     */
    public function retrieve($remote, $local) {
        $this->run_local("docker cp $this->id:$remote $local 2>&1");
    }
} 