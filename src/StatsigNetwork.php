<?php

namespace Statsig;

/**
 * From https://www.uuidgenerator.net/dev-corner/php
 */
function guidv4($data = null)
{
    // Generate 16 bytes (128 bits) of random data or use the data passed into the function.
    $data = $data ?? random_bytes(16);
    assert(strlen($data) == 16);

    // Set version to 0100
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    // Set bits 6-7 to 10
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

    // Output the 36 character UUID.
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

class StatsigNetwork
{
    private string $key;
    private string $session_id;
    function __construct()
    {
        $this->session_id = guidv4();
    }

    function setSdkKey($key)
    {
        $this->key = $key;
    }

    function downloadConfigSpecs(): ConfigSpecs
    {
        $specs = $this->postRequest("download_config_specs", json_encode((object)[]));

        $parsed_gates = [];
        for ($i = 0; $i < count($specs["feature_gates"]); $i++) {
            $parsed_gates[$specs["feature_gates"][$i]["name"]] = $specs["feature_gates"][$i];
        }

        $parsed_configs = [];
        for ($i = 0; $i < count($specs["dynamic_configs"]); $i++) {
            $parsed_configs[$specs["dynamic_configs"][$i]["name"]] = $specs["dynamic_configs"][$i];
        }

        $result = new ConfigSpecs();
        $result->gates = $parsed_gates;
        $result->configs = $parsed_configs;
        $result->fetchTime = floor(microtime(true) * 1000);

        return $result;
    }

    function checkGate(StatsigUser $user, string $gate)
    {
        $req_body = [
            'user' => $user->toLogDictionary(),
            'gateName' => $gate,
            'statsigMetadata' => StatsigMetadata::getJson()
        ];

        return $this->postRequest("check_gate", json_encode($req_body));
    }

    function getConfig(StatsigUser $user, string $config)
    {
        $req_body = [
            'user' => $user->toLogDictionary(),
            'configName' => $config,
            'statsigMetadata' => StatsigMetadata::getJson()
        ];
        return $this->postRequest("get_config", json_encode($req_body));
    }

    function logEvents($events)
    {
        $req_body = [
            'events' => $events,
            'statsigMetadata' => StatsigMetadata::getJson()
        ];
        return $this->postRequest("rgstr", json_encode($req_body));
    }

    function postRequest($endpoint, $input)
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://statsigapi.net/v1/{$endpoint}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $input,
            CURLOPT_HTTPHEADER => array(
                "STATSIG-API-KEY: {$this->key}",
                "STATSIG-SERVER-SESSION-ID: {$this->session_id}",
                "STATSIG-SDK-TYPE: " . StatsigMetadata::SDK_TYPE,
                "STATSIG-SDK-VERSION: ". StatsigMetadata::VERSION,
                'Content-Type: application/json'
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        return json_decode($response, true);
    }
}
