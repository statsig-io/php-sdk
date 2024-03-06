<?php

namespace Statsig;

use Statsig\Adapters\IDataAdapter;
use Statsig\OutputLoggers\IOutputLogger;

const NO_DOWNLOADED_COFNIG_SPEC_ERR_MESSAGE = "[Statsig]: Cannot load config specs, falling back to default values: Check if sync.php run successfully";
const CONFIG_SPEC_STALE = "[Statsig]: Config spec is possibly not up-to-date: last time polling config specs is UTC ";
const IDLIST_STALE = "[Statsig]: IDList is possibly not up-to-date: last time polling idlist is UTC ";

class StatsigStore
{
    private StatsigOptions $options;
    private IDataAdapter $data_adapter;
    private ?ConfigSpecs $specs;
    private IOutputLogger $output_logger;

    function __construct(StatsigOptions $options)
    {
        $this->options = $options;
        $this->data_adapter = $options->getDataAdapter();
        $this->specs = ConfigSpecs::loadFromDataAdapter($this->data_adapter);
        $this->output_logger = $options->getOutputLogger();
    }

    function isReadyForChecks()
    {
        return $this->specs !== null && $this->specs->fetch_time !== 0;
    }

    function getGateDefinition(string $gate)
    {
        $this->ensureSpecFreshness();

        if ($this->specs == null || !array_key_exists($gate, $this->specs->gates)) {
            return null;
        }
        return $this->specs->gates[$gate];
    }

    function getAllGates()
    {
        $this->ensureSpecFreshness();
        if ($this->specs == null) {
            return [];
        }

        return $this->specs->gates;
    }

    function getConfigDefinition(string $config)
    {
        $this->ensureSpecFreshness();

        if ($this->specs == null || !array_key_exists($config, $this->specs->configs)) {
            return null;
        }

        return $this->specs->configs[$config];
    }

    function getAllConfigs()
    {
        $this->ensureSpecFreshness();
        if ($this->specs == null) {
            return [];
        }

        return $this->specs->configs;
    }

    function getLayerDefinition(string $layer)
    {
        $this->ensureSpecFreshness();

        if ($this->specs == null || !array_key_exists($layer, $this->specs->layers)) {
            return null;
        }
        return $this->specs->layers[$layer];
    }

    function getAllLayers()
    {
        $this->ensureSpecFreshness();
        if ($this->specs == null) {
            return [];
        }

        return $this->specs->layers;
    }

    function getExperimentLayer(string $experiment)
    {
        $this->ensureSpecFreshness();

        if ($this->specs == null || !array_key_exists($experiment, $this->specs->experiment_to_layer)) {
            return null;
        }
        return $this->specs->experiment_to_layer[$experiment];
    }

    function getIDList(string $id_list_name): ?IDList
    {
        return IDList::getIDListFromAdapter($this->data_adapter, $id_list_name);
    }

    function getAppIDFromKey(string $client_sdk_key): ?string
    {
        $target_app_id = $this->specs->hashed_sdk_keys_to_app_ids[HashingUtils::djb2($client_sdk_key)] ?? null;
        if ($target_app_id != null) {
            return $target_app_id;
        }
        return $this->specs->sdk_keys_to_app_ids[$client_sdk_key] ?? null;
    }

    function ensureSpecFreshness(): void
    {
        $current_time = floor(microtime(true) * 1000);
        if ($this->specs != null && ($current_time - $this->specs->fetch_time) <= $this->options->config_freshness_threshold_ms) {
            return;
        }

        $adapter_specs = ConfigSpecs::loadFromDataAdapter($this->data_adapter);
        if ($adapter_specs == null) {
            $this->output_logger->error(NO_DOWNLOADED_COFNIG_SPEC_ERR_MESSAGE);
            return;
        }
        $this->specs = $adapter_specs;
        if ($adapter_specs != null && ($current_time - $adapter_specs->fetch_time) <= $this->options->config_freshness_threshold_ms) {
            return;
        } else {
            $formatted_fetch_time = date("Y-m-d H:i:s", floor($this->specs->fetch_time / 1000));
            $this->output_logger->warning(CONFIG_SPEC_STALE . $formatted_fetch_time);
        }
    }

    function ensureIDListsFreshness(): void
    {
        $current_time = floor(microtime(true) * 1000);
        $last_fetch_time = IDList::getLastIDListSyncTimeFromAdapter($this->data_adapter);
        if (($current_time - $last_fetch_time) <= $this->options->config_freshness_threshold_ms) {
            return;
        }
        $formatted_fetch_time = date("Y-m-d H:i:s", floor($last_fetch_time / 1000));
        $this->output_logger->warning(IDLIST_STALE . $formatted_fetch_time);
    }
}
