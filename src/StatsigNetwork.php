<?php

namespace Statsig;

// From https://www.uuidgenerator.net/dev-corner/php
use Exception;

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
    private array $statsig_metadata;
    private string $session_id;
    function __construct($version = "0.3.1")
    {
        $this->statsig_metadata = [
            'sdkType' => 'php-server',
            'sdkVersion' => $version
        ];
        $this->session_id = guidv4();
    }

    function setSdkKey($key)
    {
        $this->key = $key;
    }

    function downloadConfigSpecs()
    {
        return $this->post_request("download_config_specs", json_encode((object)[]));
    }

    function checkGate(StatsigUser $user, string $gate)
    {
        $req_body = [
            'user' => $user->toLogDictionary(),
            'gateName' => $gate,
            'statsigMetadata' => $this->statsig_metadata
        ];

        return $this->post_request("check_gate", json_encode($req_body));
    }

    function getConfig(StatsigUser $user, string $config)
    {
        $req_body = [
            'user' => $user->toLogDictionary(),
            'configName' => $config,
            'statsigMetadata' => $this->statsig_metadata
        ];
        return $this->post_request("get_config", json_encode($req_body));
    }

    function log_events($events)
    {
        $req_body = [
            'events' => $events,
            'statsigMetadata' => $this->statsig_metadata
        ];
        return $this->post_request("rgstr", json_encode($req_body));
    }

    function post_request($endpoint, $input)
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
                "STATSIG-SDK-TYPE: {$this->statsig_metadata['sdkType']}",
                "STATSIG-SDK-VERSION: {$this->statsig_metadata['sdkVersion']}",
                'Content-Type: application/json'
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        return json_decode($response, true);
    }
}
