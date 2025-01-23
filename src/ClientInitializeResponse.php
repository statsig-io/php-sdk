<?php

namespace Statsig;

class ClientInitializeResponse
{
    private StatsigUser $user;
    private StatsigStore $store;
    private \Closure $eval_func;
    private ?string $client_sdk_key;
    private ?string $hash;

    function __construct(StatsigUser $user, StatsigStore $store, callable $eval_func, ?string $client_sdk_key, ?string $hash)
    {
        $this->user = $user;
        $this->store = $store;
        $this->eval_func = \Closure::fromCallable($eval_func);
        $this->client_sdk_key = $client_sdk_key;
        $this->hash = $hash;
    }

    private function getEvalResult($config_spec): ConfigEvaluation
    {
        return ($this->eval_func)($this->user, $config_spec);
    }

    private function hashName(string $name)
    {
        if ($this-> hash == "none") {
            return $name;
        }
        if ($this->hash == "djb2") {
            return HashingUtils::djb2($name);
        }
        return base64_encode(hash("sha256", $name, true));
    }

    private function hashExposures(array $exposures)
    {
        $hashed_exposures = [];
        foreach ($exposures as $exposure) {
            $hashed_exposures[] = array(
                "gate" => $this->hashName($exposure["gate"]),
                "gateValue" => $exposure["gateValue"],
                "ruleID" => $exposure["ruleID"],
            );
        }
        return $hashed_exposures;
    }

    private function evalResultToBaseResponse(string $name, ConfigEvaluation $eval_result)
    {
        $hashed_name = $this->hashName($name);
        $base_response = array(
            "name" => $hashed_name,
            "rule_id" => $eval_result->rule_id,
            "secondary_exposures" => $this->hashExposures($eval_result->secondary_exposures),
        );
        return array($hashed_name, $base_response);
    }

    private function gateToResponse(string $gate_name, $config_spec)
    {
        $eval_result = $this->getEvalResult($config_spec);
        list($hashed_name, $base_response) = $this->evalResultToBaseResponse($gate_name, $eval_result);
        $result = array_merge($base_response, array(
            "value" => $eval_result->bool_value,
            "id_type" => $config_spec["idType"],
        ));
        return array($hashed_name, $result);
    }

    private function configToResponse(string $config_name, $config_spec)
    {
        $eval_result = $this->getEvalResult($config_spec);
        list($hashed_name, $base_response) = $this->evalResultToBaseResponse($config_name, $eval_result);
        $result = array_merge($base_response, array(
            "value" => $eval_result->json_value,
            "group" => $eval_result->rule_id,
            "is_device_based" => strtolower($config_spec["idType"] ?? "") === "stableid",
            "id_type" => $config_spec["idType"],
        ));
        if ($eval_result->group_name != null) {
            $result["group_name"] = $eval_result->group_name;
        }
        $entity_type = strtolower($config_spec["entity"] ?? "");
        if ($entity_type === "dynamic_config") {
            $result["passed"] = $eval_result->bool_value;
        }
        if ($entity_type === "experiment") {
            $result["is_user_in_experiment"] = $eval_result->is_experiment_group;
            $result["is_experiment_active"] = $config_spec["isActive"];
            if ($config_spec["hasSharedParams"]) {
                $result["is_in_layer"] = true;
                $result["explicit_parameters"] = $config_spec["explicitParameters"] ?? [];
                $layer_name = $this->store->getExperimentLayer($config_name) ?? "";
                $layer = $this->store->getLayerDefinition($layer_name);
                if ($layer !== null) {
                    $layer_value = $layer["defaultValue"] ?? (object)[];
                    $result["value"] = array_merge($result["value"], $layer_value);
                }
            }
        }

        $value = $result['value'];
        if (count($value) == 0) {
            $result['value'] = (object)[];
        }

        return array($hashed_name, $result);
    }

    function layerToResponse(string $layer_name, $config_spec)
    {
        $eval_result = $this->getEvalResult($config_spec);
        list($hashed_name, $base_response) = $this->evalResultToBaseResponse($layer_name, $eval_result);
        $result = array_merge($base_response, array(
            "value" => $eval_result->json_value,
            "group" => $eval_result->rule_id,
            "is_device_based" => strtolower($config_spec["idType"] ?? "") === "stableid",
            "undelegated_secondary_exposures" => $this->hashExposures($eval_result->undelegated_secondary_exposures),
            "explicit_parameters" => $config_spec["explicitParameters"] ?? [],
        ));
        $delegate = $eval_result->allocated_experiment;
        if ($delegate !== "") {
            $delegate_spec = $this->store->getConfigDefinition($delegate);
            $delegate_result = $this->getEvalResult($delegate_spec);
            if ($delegate_spec !== null) {
                $result["allocated_experiment_name"] = $this->hashName($delegate);
                $result["is_user_in_experiment"] = $delegate_result->is_experiment_group;
                $result["is_experiment_active"] = $delegate_spec["isActive"] ?? false;
                $result["explicit_parameters"] = $delegate_spec["explicitParameters"] ?? [];
                if ($delegate_result->group_name != null) {
                    $result["group_name"] = $delegate_result->group_name;
                }
            }
        }

        $value = $result['value'];
        if (count($value) == 0) {
            $result['value'] = (object)[];
        }

        return array($hashed_name, $result);
    }

    function getFormattedResponse()
    {
        if ($this->store->getTime() == 0) {
            return [];
        }
        $target_app_id = null;
        if ($this->client_sdk_key !== null) {
            $target_app_id = $this->store->getAppIDFromKey($this->client_sdk_key);
        }

        $feature_gates = [];
        foreach ($this->store->getAllGates() as $name => $spec) {
            $entity_type = strtolower($spec["entity"]);
            $target_app_ids = $spec["targetAppIDs"] ?? [];
            if ($entity_type !== "segment" && $entity_type !== "holdout" 
                && ($target_app_id === null || in_array($target_app_id, $target_app_ids))) {
                list($hashed_name, $res) = $this->gateToResponse($name, $spec);
                $feature_gates[$hashed_name] = $res;
            }
        }
        $dynamic_configs = [];
        foreach ($this->store->getAllConfigs() as $name => $spec) {
            $target_app_ids = $spec["targetAppIDs"] ?? [];
            if ($target_app_id === null || in_array($target_app_id, $target_app_ids)) {
                list($hashed_name, $res) = $this->configToResponse($name, $spec);
                $dynamic_configs[$hashed_name] = $res;
            }
        }
        $layer_configs = [];
        foreach ($this->store->getAllLayers() as $name => $spec) {
            $target_app_ids = $spec["targetAppIDs"] ?? [];
            if ($target_app_id === null || in_array($target_app_id, $target_app_ids)) {
                list($hashed_name, $res) = $this->layerToResponse($name, $spec);
                $layer_configs[$hashed_name] = $res;
            }
        }
        $evaluated_keys = [];
        if ($this->user->getUserID()) {
            $evaluated_keys["userID"] = $this->user->getUserID();
        }
        if ($this->user->getCustomIDs()) {
            $evaluated_keys["customIDs"] = $this->user->getCustomIDs();
        }

        $response = array(
            "feature_gates" => $feature_gates,
            "dynamic_configs" => $dynamic_configs,
            "layer_configs" => $layer_configs,
            "sdkParams" => (object)[],
            "has_updates" => true,
            "generator" => "statsig-php-sdk",
            "evaluated_keys" => $evaluated_keys,
            "time" => $this->store->getTime(),
            "user" => $this->user->toLogDictionary(),
            "hash_used" => $this->hash ?? 'sha256',
            "sdkInfo" => (object)[
                "sdkType" => StatsigMetadata::SDK_TYPE,
                "sdkVersion" => StatsigMetadata::VERSION,
            ],
        );
        return $response;
    }
}