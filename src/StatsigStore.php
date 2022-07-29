<?php

namespace Statsig;

use Statsig\Adapters\IConfigAdapter;

class StatsigStore
{
    private ConfigSpecs $specs;

    function __construct(IConfigAdapter $config_adapter)
    {
        $this->specs = $config_adapter->getConfigSpecs() ?? new ConfigSpecs();
    }

    function getGateDefinition(string $gate)
    {
        if (!array_key_exists($gate, $this->specs->gates)) {
            return null;
        }
        return $this->specs->gates[$gate];
    }

    function getConfigDefinition(string $config)
    {
        if (!array_key_exists($config, $this->specs->configs)) {
            return null;
        }
        return $this->specs->configs[$config];
    }
}
