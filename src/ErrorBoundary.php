<?php

namespace Statsig;

use Statsig\Exceptions\StatsigBaseException;
use Exception;

class ErrorBoundary
{
    private string $api_key;
    private array $seen;
    private \GuzzleHttp\Client $client;
    private SecretRedactor $redactor;

    function __construct(string $api_key)
    {
        $this->api_key = $api_key;
        $this->redactor = new SecretRedactor($api_key);
        $this->seen = [];
        $this->client = new \GuzzleHttp\Client();
    }

    function getEndpoint(): string
    {
        return 'https://statsigapi.net/v1/sdk_exception';
    }

    function capture(callable $task, callable $recover, string $tag = '', $extra = [])
    {
        try {
            return $task();
        } catch (StatsigBaseException $e) {
            throw $e;
        } catch (Exception $e) {
            $this->logException($e, $tag, $extra);
            return $recover();
        }
    }

    function swallow(callable $task)
    {
        $empty_recover = function () {
        };
        $this->capture($task, $empty_recover);
    }

    function logException(Exception $e, string $tag = '', array $extra = [], bool $force = false)
    {
        try {
            $name = $this->redactor->text($e->__toString());
            if (!$force && ($this->api_key === null || in_array($name, $this->seen))) {
                return;
            }
            array_push($this->seen, $name);
            $body = array_merge([
                'exception' => $name,
                'info' => $this->redactor->text($e->getTraceAsString()),
                'statsigMetadata' => StatsigMetadata::getJson(),
                'tag' => $this->redactor->text($tag)
            ], $this->redactor->value($extra));
            $this->client->request('POST', $this->getEndpoint(), [
                'max' => 10,
                'protocols' => ['https'],
                'timeout' => 20,
                'version' => 2.0,
                'body' => json_encode($body),
                'headers' => [
                    'STATSIG-API-KEY' => $this->api_key,
                    'STATSIG-SDK-TYPE' => StatsigMetadata::SDK_TYPE,
                    'STATSIG-SDK-VERSION' => StatsigMetadata::VERSION,
                    'Content-Type' => 'application/json',
                ]
            ]);
        } catch (Exception $e) {
        }
    }
}
