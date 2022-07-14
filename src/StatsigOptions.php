<?php

namespace Statsig;

class StatsigOptions {
    private $configFile;
    private $logOutputFile;
    public $tier;

    function __construct($configFile, $logOutput = null) {
        if ($configFile[0] !== '/') {
            $configFile = __DIR__ . '/' . $configFile;
        }
        $this->configFile = $configFile;

        if (empty($logOutput)) {
            $this->logOutputFile = null;
            return;
        }

        if ($logOutput[0] !== '/') {
            $logOutput = __DIR__ . '/' . $logOutput;
        }
        $this->logOutputFile = $logOutput;
    }

    function getConfigFile() {
        return $this->configFile;
    }

    function getLogOutputFile() {
        return $this->logOutputFile;
    }

    function setEnvironmentTier($tier) {
        $this->tier = $tier;
    }
}