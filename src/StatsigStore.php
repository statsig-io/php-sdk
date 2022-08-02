<?php

namespace Statsig;

use Statsig\Adapters\AdapterUtils;
use Statsig\Adapters\IConfigAdapter;

class StatsigStore
{
    private StatsigNetwork $network;
    private IConfigAdapter $config_adapter;
    private ?ConfigSpecs $specs;

    private const ONE_MINUTE_IN_MS = 1000 * 60;

    function __construct(StatsigNetwork $network, IConfigAdapter $config_adapter)
    {
        $this->network = $network;
        $this->config_adapter = $config_adapter;
        $this->specs = $config_adapter->getConfigSpecs();
    }

    function getGateDefinition(string $gate)
    {
        $this->ensureSpecFreshness();

        if (!array_key_exists($gate, $this->specs->gates)) {
            return null;
        }
        return $this->specs->gates[$gate];
    }

    function getConfigDefinition(string $config)
    {
        $this->ensureSpecFreshness();

        if (!array_key_exists($config, $this->specs->configs)) {
            return null;
        }
        return $this->specs->configs[$config];
    }

    function ensureSpecFreshness()
    {
        $current_time = floor(microtime(true) * 1000);
        if ($this->specs != null && ($current_time - $this->specs->fetchTime) <= self::ONE_MINUTE_IN_MS) {
            return;
        }

        $adapter_specs = $this->config_adapter->getConfigSpecs();
        if ($adapter_specs != null && ($current_time - $adapter_specs->fetchTime) <= self::ONE_MINUTE_IN_MS) {
            $this->specs = $adapter_specs;
            return;
        }

        $network_specs = $this->network->downloadConfigSpecs();
        $this->config_adapter->updateConfigSpecs($network_specs);
        $this->specs = $network_specs;
    }
}
