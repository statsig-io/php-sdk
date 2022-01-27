<?php

namespace Statsig;

class StatsigNetwork {
    private $key;
    private $statsigMetadata;
    function __construct($version = "0.1.0") {
        $metadata = (object)[];
        $metadata->sdkType = "php-server";
        $metadata->sdkVersion = $version;
        $this->statsigMetadata = $metadata;
    }

    function setSdkKey($key) {
        $this->key = $key;
    }

    function downloadConfigSpecs() {
        return $this->post_request("download_config_specs", json_encode((object)[]));
    }

    function checkGate($user, $gate) {
        $req_body = (object)[];
        $req_body->user = $user;
        $req_body->gateName = $gate;
        $req_body->statsigMetadata = $this->statsigMetadata;
        return $this->post_request("check_gate", json_encode($req_body));
    }

    function getConfig($user, $config) {
        $req_body = (object)[];
        $req_body->user = $user;
        $req_body->configName = $config;
        $req_body->statsigMetadata = $this->statsigMetadata;
        return $this->post_request("get_config", json_encode($req_body));
    }

    function log_events($events) {
        $req_body = (object)[];
        $req_body->events = $events;
        $req_body->statsigMetadata = $this->statsigMetadata;
        return $this->post_request("rgstr", json_encode($req_body));
    }

    function post_request($endpoint, $input) {
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
                "statsig-api-key: {$this->key}",
                'Content-Type: application/json'
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        return json_decode($response, true);
    }
}