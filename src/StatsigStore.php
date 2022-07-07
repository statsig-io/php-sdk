<?php

namespace Statsig;

class StatsigStore {
    private $file;
    private $specs;
    private $gates;

    function __construct($file) {
        $this->gates = [];
        $this->configs = [];
        $this->file = null;
        try {
            $contents = @file_get_contents($file);
            if ($contents !== false) {
                $this->file = $file;
                $specs = json_decode($contents, true);
    
                $this->gates = $specs["gates"];
                $this->configs = $specs["configs"];
            }
        } catch (Exception $e) {}
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