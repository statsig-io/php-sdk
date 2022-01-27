<?php

namespace Statsig;

class StatsigStore {
    private $file;
    private $specs;
    private $gates;

    function __construct($file) {
        $this->file = $file;
        $specs = json_decode(file_get_contents($this->file), true);

        $this->gates = $specs["gates"];
        $this->configs = $specs["configs"];
    }

    function getGateDefinition($gate) {
        if (!array_key_exists($gate, $this->gates)) {
            return null;
        }
        return $this->gates[$gate];
    }

    function getConfigDefinition($config) {
        if (!array_key_exists($config, $this->configs)) {
            return null;
        }
        return $this->configs[$config];
    }
}