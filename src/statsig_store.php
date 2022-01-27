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
            } else {
                echo "Failed to read statsig config file, returning default values";
            }
        } catch (Exception $e) {
            echo "Failed to read statsig config file, returning default values";
            echo $e->getMessage();
        }
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