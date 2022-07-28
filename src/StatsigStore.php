<?php

namespace Statsig;

use Exception;

class StatsigStore
{
    private array $gates;
    private array $configs;

    function __construct(string $config_file_path)
    {
        $this->gates = [];
        $this->configs = [];

        try {
            $contents = @file_get_contents($config_file_path);
            if ($contents !== false) {
                $specs = json_decode($contents, true);

                $this->gates = $specs["gates"];
                $this->configs = $specs["configs"];
            }
        } catch (Exception $e) {
        }
    }

    function getGateDefinition(string $gate)
    {
        if (!array_key_exists($gate, $this->gates)) {
            return null;
        }
        return $this->gates[$gate];
    }

    function getConfigDefinition(string $config)
    {
        if (!array_key_exists($config, $this->configs)) {
            return null;
        }
        return $this->configs[$config];
    }
}
