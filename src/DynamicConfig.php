<?php

namespace Statsig;

class DynamicConfig {

    private $name;
    private $jsonValue;
    private $ruleID;
    private $secondaryExposures;

    function __construct($name, $jsonValue = [], $ruleID = "", $secondaryExposures = []) {
        $this->name = $name;
        $this->jsonValue = $jsonValue;
        $this->ruleID = $ruleID;
        $this->secondaryExposures = $secondaryExposures;
    }

    function get($field, $default) {
        if (!array_key_exists($field, $this->jsonValue)) {
            return $default;
        }
        $val = $this->jsonValue[$field];
        if ($default !== null && gettype($val) !== gettype($default)) {
            return $default;
        }
        return $val;
    }

    function getValue() {
        return $this->jsonValue;
    }

    function getRuleID() {
        return $this->ruleID;
    }
}