<?php

namespace Statsig;

use Statsig\Exceptions\InvalidSDKKeyException;

class StatsigServer
{
    private StatsigNetwork $network;
    private Evaluator $evaluator;
    private StatsigLogger $logger;
    private StatsigOptions $options;
    private StatsigStore $store;
    private ErrorBoundary $error_boundary;

    function __construct(string $sdk_key, StatsigOptions $options)
    {
        if (substr($sdk_key, 0, 7) !== 'secret-') {
            $e = new InvalidSDKKeyException('Invalid key provided.  You must use a Server Secret Key from the Statsig console.');
            $e->logToStderr();
        }
        $this->error_boundary = new ErrorBoundary($sdk_key);
        $this->network = new StatsigNetwork();
        $this->network->setSdkKey($sdk_key);

        $this->store = new StatsigStore($this->network, $options);
        $this->evaluator = new Evaluator($this->store);
        $this->logger = new StatsigLogger($options, $this->network);
        $this->options = $options;
    }

    function __destruct()
    {
        $this->logger->flush();
    }

    function checkGate(StatsigUser $user, string $gate): bool
    {
        $task = function () use ($user, $gate) {
            $user = $this->normalizeUser($user);
            $res = $this->evaluator->checkGate($user, $gate);
            if ($res->fetch_from_server) {
                $net_result = $this->network->checkGate($user, $gate);
                return key_exists("value", $net_result ?? []) && $net_result["value"] === true;
            }
            $this->logger->logGateExposure(
                $user,
                $gate,
                $res->bool_value,
                $res->rule_id,
                $res->secondary_exposures,
            );
            return $res->bool_value;
        };
        $fallback = function () {
            return false;
        };
        return $this->error_boundary->capture($task, $fallback);
    }

    function getConfig(StatsigUser $user, string $config): DynamicConfig
    {
        $task = function () use ($user, $config) {
            $user = $this->normalizeUser($user);
            $res = $this->evaluator->getConfig($user, $config);
            if ($res->fetch_from_server) {
                $net_result = $this->network->getConfig($user, $config);
                if (key_exists("value", $net_result ?? []) && gettype($net_result["value"]) === 'array') {
                    return new DynamicConfig($config, $net_result["value"], $net_result["rule_id"]);
                }
                return new DynamicConfig($config);
            }
            $this->logger->logConfigExposure(
                $user,
                $config,
                $res->rule_id,
                $res->secondary_exposures,
            );
            return new DynamicConfig($config, $res->json_value, $res->rule_id);
        };
        $fallback = function () use ($config) {
            return new DynamicConfig($config);
        };
        return $this->error_boundary->capture($task, $fallback);
    }

    function getExperiment(StatsigUser $user, string $experiment): DynamicConfig
    {
        $task = function () use ($user, $experiment) {
            $user = $this->normalizeUser($user);
            return $this->getConfig($user, $experiment);
        };
        $fallback = function () use ($experiment) {
            return new DynamicConfig($experiment);
        };
        return $this->error_boundary->capture($task, $fallback);
    }

    function logEvent(StatsigEvent $event)
    {
        $task = function () use ($event) {
            if ($this->options->tier !== null) {
                $event->user['statsigEnvironment'] = ["tier" => $this->options->tier];
            }
            $this->logger->log($event);
        };
        return $this->error_boundary->swallow($task);
    }

    function flush()
    {
        $this->logger->flush();
    }

    private function normalizeUser(StatsigUser $user): StatsigUser
    {
        $new_user = clone $user;
        if ($this->options->tier !== null) {
            $new_user->setStatsigEnvironment(["tier" => $this->options->tier]);
        }

        $new_user->assertUserIsIdentifiable();
        return $new_user;
    }
}
