<?php

namespace Statsig;

use Statsig\Adapters\IDataAdapter;

class ConfigSpecs
{
    private const RULESETS_KEY = "statsig.cache";

    public int $fetch_time;
    public int $time;
    public array $gates = [];
    public array $configs = [];
    public array $layers = [];
    public array $experiment_to_layer;
    public array $sdk_keys_to_app_ids = [];
    public array $hashed_sdk_keys_to_app_ids = [];

    public static function sync(IDataAdapter $adapter, StatsigNetwork $network): ?ConfigSpecs
    {
        $json = $network->postRequest("download_config_specs", json_encode((object)[]))["body"];
        $specs = ConfigSpecs::fromJson($json, $network->getSDKKey());

        if ($specs !== null) {
            $json = json_encode([
                "fetch_time" => $specs->fetch_time,
                "time" => $specs->time,
                "gates" => $specs->gates,
                "configs" => $specs->configs,
                "layers" => $specs->layers,
                "experiment_to_layer" => $specs->experiment_to_layer,
                "sdk_keys_to_app_ids" => $specs->sdk_keys_to_app_ids,
                "hashed_sdk_keys_to_app_ids" => $specs->hashed_sdk_keys_to_app_ids
            ]);
            $adapter->set(self::RULESETS_KEY, $json);
        }

        return $specs;
    }

    public static function loadFromDataAdapter(IDataAdapter $adapter): ?ConfigSpecs
    {
        $json = @json_decode($adapter->get(self::RULESETS_KEY), true, 512, JSON_BIGINT_AS_STRING);
        if ($json === null) {
            return null;
        }

        $result = new ConfigSpecs();
        $result->gates = $json["gates"] ?? [];
        $result->configs = $json["configs"] ?? [];
        $result->layers = $json["layers"] ?? [];
        $result->experiment_to_layer = $json["experiment_to_layer"] ?? [];
        $result->fetch_time = $json["fetch_time"] ?? 0;
        $result->sdk_keys_to_app_ids = $json["sdk_keys_to_app_ids"] ?? [];
        $result->hashed_sdk_keys_to_app_ids = $json["hashed_sdk_keys_to_app_ids"] ?? [];
        $result->time = $json["time"] ?? 0;
        return $result;
    }

    private static function fromJson(?array $json, string $server_secret): ?ConfigSpecs
    {
        if ($json == null) {
            return null;
        }
        if (isset($json["hashed_sdk_key_used"])) {
            $hashedSDKKeyUsed = $json["hashed_sdk_key_used"];
            if ($hashedSDKKeyUsed !== null && $hashedSDKKeyUsed !== HashingUtils::djb2($server_secret)) {
                return null;
            }
        }
        

        $parsed_gates = [];
        for ($i = 0; $i < count($json["feature_gates"] ?? []); $i++) {
            $parsed_gates[$json["feature_gates"][$i]["name"]] = $json["feature_gates"][$i];
        }

        $parsed_configs = [];
        for ($i = 0; $i < count($json["dynamic_configs"] ?? []); $i++) {
            $parsed_configs[$json["dynamic_configs"][$i]["name"]] = $json["dynamic_configs"][$i];
        }

        $parsed_layers = [];
        for ($i = 0; $i < count($json["layer_configs"] ?? []); $i++) {
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

        $parsed_sdk_keys_to_app_ids = [];
        if (isset($json["sdk_keys_to_app_ids"])) {
            $parsed_sdk_keys_to_app_ids = $json["sdk_keys_to_app_ids"];
        }

        $parsed_hashed_sdk_keys_to_app_ids = [];
        if (isset($json["hashed_sdk_keys_to_app_ids"])) {
            $parsed_hashed_sdk_keys_to_app_ids = $json["hashed_sdk_keys_to_app_ids"];
        }

        $result = new ConfigSpecs();
        $result->gates = $parsed_gates;
        $result->configs = $parsed_configs;
        $result->layers = $parsed_layers;
        $result->experiment_to_layer = $parsed_experiment_to_layer;
        $result->fetch_time = floor(microtime(true) * 1000);
        $result->sdk_keys_to_app_ids = $parsed_sdk_keys_to_app_ids;
        $result->hashed_sdk_keys_to_app_ids = $parsed_hashed_sdk_keys_to_app_ids;
        $result->time = $json["time"] ?? 0;
        return $result;
    }
}
