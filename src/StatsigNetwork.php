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

namespace Statsig;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

class StatsigNetwork
{
    private string $key;
    private string $session_id;
    private array $guzzle_options;
    private Client $client;

    function __construct()
    {
        $this->session_id = guidv4();
        $this->guzzle_options = [];
        
    }

    function setOptions(array $options)
    {
        $this->guzzle_options = $options;
    }

    function setSdkKey(string $key)
    {
        $this->key = $key;
        $this->client = new Client([
          'base_uri' => 'https://statsigapi.net/v1/',
          'headers' => [
              'STATSIG-API-KEY' => $this->key,
              'STATSIG-SERVER-SESSION-ID' => $this->session_id,
              'STATSIG-SDK-TYPE' => StatsigMetadata::SDK_TYPE,
              'STATSIG-SDK-VERSION' => StatsigMetadata::VERSION,
              'Content-Type' => 'application/json'
          ]
      ]);
    }

    function logEvents($events)
    {
        $req_body = [
            'events' => $events,
            'statsigMetadata' => StatsigMetadata::getJson()
        ];
        return $this->postRequest("rgstr", json_encode($req_body));
    }


    function postRequest(string $endpoint, string $input)
    {
        $response = $this->client->post($endpoint, [
            RequestOptions::BODY => $input,
            RequestOptions::HTTP_ERRORS => false,
        ] + $this->guzzle_options); 

        $body = $response->getBody()->getContents();
        
        return json_decode($body, true, 512, JSON_BIGINT_AS_STRING);
    }

    function multiGetRequest($requests): array
    {
        $multi = curl_multi_init();
        $responses = [];

        foreach ($requests as $key => $value) {
            $curl = curl_init();
            $responses[$key] = [
                "headers" => [],
                "curl" => $curl
            ];
            $headers = &$responses[$key]["headers"];

            curl_setopt_array($curl, [
                CURLOPT_URL => $value["url"],
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_HTTPHEADER => $value["headers"],
                CURLOPT_HEADERFUNCTION => function ($curl, $header) use (&$headers) {
                    $len = strlen($header);
                    $header = explode(':', $header, 2);
                    if (count($header) >= 2) {
                        $headers[strtolower(trim($header[0]))][] = trim($header[1]);
                    }
                    return $len;
                }
            ]);
            curl_multi_add_handle($multi, $curl);
        }

        $index = null;
        do {
            curl_multi_exec($multi, $index);
        } while ($index > 0);

        foreach ($responses as $key => $value) {
            $content = curl_multi_getcontent($value["curl"]);
            $responses[$key] = [
                "headers" => $value["headers"],
                "data" => $content
            ];
            curl_multi_remove_handle($multi, $curl);
        }

        return $responses;
    }
}
