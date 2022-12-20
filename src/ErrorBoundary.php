<?php

namespace Statsig;

use Statsig\Exceptions\EventQueueSizeException;
use Statsig\Exceptions\InvalidSDKKeyException;
use Statsig\Exceptions\StatsigUserException;
use Exception;

class ErrorBoundary
{
    const ENDPOINT = 'https://statsigapi.net/v1/sdk_exception';
    private string $api_key;
    private array $seen;

    function __construct($api_key)
    {
        $this->api_key = $api_key;
        $this->seen = [];
    }

    function capture(callable $task, callable $recover, ...$args)
    {
        try {
            return $task(...$args);
        } catch (EventQueueSizeException | StatsigUserException | InvalidSDKKeyException $e) {
            throw $e;
        } catch (Exception $e) {
            $this->logException($e);
            return $recover();
        }
    }

    function swallow(callable $task)
    {
        $empty_recover = function () {
        };
        $this->capture($task, $empty_recover);
    }

    function logException(Exception $e)
    {
        try {
            $name = $e->__toString();
            if ($this->api_key === null || in_array($name, $this->seen)) {
                return;
            }
            $body = [
                'exception' => $name,
                'info' => $e->getTraceAsString(),
                'statsigMetadata' => StatsigMetadata::getJson()
            ];
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => ErrorBoundary::ENDPOINT,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($body),
                CURLOPT_HTTPHEADER => array(
                    "STATSIG-API-KEY: {$this->api_key}",
                    "STATSIG-SDK-TYPE: " . StatsigMetadata::SDK_TYPE,
                    "STATSIG-SDK-VERSION: " . StatsigMetadata::VERSION,
                    'Content-Type: application/json'
                ),
            ));
            curl_exec($curl);
        } catch (Exception $e) {
        }
    }
}
