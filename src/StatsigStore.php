<?php

namespace Statsig;

use Statsig\Adapters\IDataAdapter;

class StatsigStore
{
    private StatsigNetwork $network;
    private StatsigOptions $options;
    private IDataAdapter $data_adapter;
    private ?ConfigSpecs $specs;

    function __construct(StatsigNetwork $network, StatsigOptions $options)
    {
        $this->network = $network;
        $this->options = $options;
        $this->data_adapter = $options->getDataAdapter();
        $this->specs = ConfigSpecs::loadFromDataAdapter($this->data_adapter);
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

    function getLayerDefinition(string $layer)
    {
        $this->ensureSpecFreshness();

        if (!array_key_exists($layer, $this->specs->layers)) {
            return null;
        }
        return $this->specs->layers[$layer];
    }

    function getExperimentLayer(string $experiment)
    {
        $this->ensureSpecFreshness();

        return $this->specs->experiment_to_layer->get($experiment, null);
    }

    function getIDList(string $id_list_name): ?IDList
    {
        return IDList::getIDListFromAdapter($this->data_adapter, $id_list_name);
    }

    function ensureSpecFreshness()
    {
        $current_time = floor(microtime(true) * 1000);
        if ($this->specs != null && ($current_time - $this->specs->fetch_time) <= $this->options->config_freshness_threshold_ms) {
            return;
        }

        $adapter_specs = ConfigSpecs::loadFromDataAdapter($this->data_adapter);
        if ($adapter_specs != null && ($current_time - $adapter_specs->fetch_time) <= $this->options->config_freshness_threshold_ms) {
            $this->specs = $adapter_specs;
            return;
        }

        $this->specs = ConfigSpecs::sync($this->data_adapter, $this->network);
    }
}
