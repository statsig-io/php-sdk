<?php

namespace Statsig;

class StatsigOptions {
    private $configFile;
    private $logOutputFile;

    function __construct($configFile, $logOutput) {
        if ($configFile[0] !== '/') {
            $configFile = __DIR__ . '/' . $configFile;
        }
        $this->configFile = $configFile;

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
}