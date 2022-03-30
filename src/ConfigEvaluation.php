<?php

namespace Statsig;

class ConfigEvaluation {
    public $boolValue;
    public $jsonValue;
    public $ruleID;
    public $fetchFromServer;
    public $secondaryExposures;

    function __construct($boolValue, $ruleID = "", $jsonValue = [], $secondaryExposures = [], $fetchFromServer = false) {
        $this->boolValue = $boolValue;
        $this->ruleID = $ruleID;
        $this->jsonValue = $jsonValue;
        $this->secondaryExposures = $secondaryExposures;
        $this->fetchFromServer = $fetchFromServer;
    }
}
