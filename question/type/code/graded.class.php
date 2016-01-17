<?php

class GradedCode {
    private static $table = "qtype_code_coderuns";
    public $id;
    public $runid;
    public $output;
    private static $debug = false;

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
        echo "<pre>[GRADED] $var</pre>";
    }

    public static function createRun() {
        /** @var moodle_database $DB */
        global $DB;

        return $DB->insert_record(self::$table, (object) [
            "runid" => "0",
            "graded" => "{}"
        ], true);
    }

    public function __construct($id) {
        /** @var moodle_database $DB */
        global $DB;
        $this->id = $id;

        $record = $DB->get_record(self::$table, [
            "id" => $id
        ]);

        if($record === false) {
            throw new InvalidArgumentException();
        }

        self::debug($record);

        $this->output = (array) json_decode($record->graded);
        $this->runid = $record->runid;
    }

    public static function fieldFromRunID($runid) {

    }

    /**
     * Sets the
     * @param string $output
     */
    public function setOutput($output) {
        /** @var moodle_database $DB */
        global $DB;

        $DB->update_record(self::$table, (object) [
            "id" => $this->id,
            "runid" => $output["runid"],
            "graded" => json_encode($output)
        ]);
    }

    /**
     * @param string $raw the raw runid param
     * @param string $secret the secret for encryption
     * @return bool|int the run id for false on failure
     */
    public static function validateRunID($raw, $secret) {
        self::debugt("Validating $raw with secret $secret");
        $decode = base64_decode($raw);
        if($decode == false) {
            return false;
        }
        self::debugt("Value: $decode");
        $parts = [];
        $result = preg_match("/([0-9]+):([a-f0-9]+)/", $decode, $parts);
        self::debugt("Preg result: $result " . implode(";", $parts));
        if(!$result) {
            return false;
        }
        $shouldBe = hash_hmac("sha256", $parts[1], $secret);
        self::debugt("Compare $shouldBe and $parts[2]");
        if($shouldBe != $parts[2]) {
            return false;
        }

        return intval($parts[1]);
    }

    public static function getRunID($id, $secret) {
        return base64_encode("$id:".hash_hmac("sha256", $id, $secret));
    }
}