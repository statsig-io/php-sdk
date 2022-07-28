<?php

namespace Statsig;

use Statsig\Adapters\IConfigAdapter;

class StatsigStore
{
    private array $gates;
    private array $configs;

    function __construct(IConfigAdapter $config_adapter)
    {
        $specs = $config_adapter->getConfigSpecs();
        $this->gates = $specs["gates"] ?? [];
        $this->configs = $specs["configs"] ?? [];
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
