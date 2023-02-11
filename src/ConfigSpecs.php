<?php

namespace Statsig;

use Statsig\Adapters\IDataAdapter;

class ConfigSpecs
{
    private const RULESETS_KEY = "statsig.cache";

    public int $fetch_time;
    public array $gates = [];
    public array $configs = [];
    public array $layers = [];
    public array $experiment_to_layer;

    public static function sync(IDataAdapter $adapter, StatsigNetwork $network): ?ConfigSpecs
    {
        $json = $network->postRequest("download_config_specs", json_encode((object)[]));
        $specs = ConfigSpecs::fromJson($json);

        if ($specs !== null) {
            $json = json_encode([
                "fetch_time" => $specs->fetch_time,
                "gates" => $specs->gates,
                "configs" => $specs->configs,
                "layers" => $specs->layers,
                "experiment_to_layer" => $specs->experiment_to_layer
            ]);
            $adapter->set(self::RULESETS_KEY, $json);
        }

        return $specs;
    }

    public static function loadFromDataAdapter(IDataAdapter $adapter): ?ConfigSpecs
    {
        $json = @json_decode($adapter->get(self::RULESETS_KEY), true);
        if ($json === null) {
            return null;
        }

        $result = new ConfigSpecs();
        $result->gates = $json["gates"] ?? [];
        $result->configs = $json["configs"] ?? [];
        $result->layers = $json["layers"] ?? [];
        $result->experiment_to_layer = $json["experiment_to_layer"] ?? [];
        $result->fetch_time = $json["fetch_time"] ?? 0;
        return $result;
    }

    private static function fromJson(?array $json): ?ConfigSpecs
    {
        if ($json == null) {
            return null;
        }

        $parsed_gates = [];
        for ($i = 0; $i < count($json["feature_gates"]); $i++) {
            $parsed_gates[$json["feature_gates"][$i]["name"]] = $json["feature_gates"][$i];
        }

        $parsed_configs = [];
        for ($i = 0; $i < count($json["dynamic_configs"]); $i++) {
            $parsed_configs[$json["dynamic_configs"][$i]["name"]] = $json["dynamic_configs"][$i];
        }

        $parsed_layers = [];
        for ($i = 0; $i < count($json["layer_configs"]); $i++) {
            $parsed_layers[$json["layer_configs"][$i]["name"]] = $json["layer_configs"][$i];
        }

        $parsed_experiment_to_layer = [];
        if (isset($json["layers"])) {
            foreach ($json["layers"] as $layer_name => $experiments) {
                foreach ($experiments as $experiment) {
                    $experiment_to_layer[$experiment] = $layer_name;
                }
            }
        }

        $result = new ConfigSpecs();
        $result->gates = $parsed_gates;
        $result->configs = $parsed_configs;
        $result->layers = $parsed_layers;
        $result->experiment_to_layer = $parsed_experiment_to_layer;
        $result->fetch_time = floor(microtime(true) * 1000);
        return $result;
    }
}
