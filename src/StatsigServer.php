<?php

namespace Statsig;

use Statsig\Exceptions\InvalidSDKKeyException;
use Statsig\Logger\ILogger;

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

        $this->store = new StatsigStore($options);
        $this->evaluator = new Evaluator($this->store);
        $this->logger = new StatsigLogger($options, $this->network);
        $this->options = $options;
    }

    function __destruct()
    {
        $this->logger->shutdown();
    }

    function checkGate(StatsigUser $user, string $gate): bool
    {
        $task = function () use ($user, $gate) {
            $user = $this->normalizeUser($user);
            $res = $this->evaluator->checkGate($user, $gate);
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
            $this->logger->logConfigExposure(
                $user,
                $config,
                $res->rule_id,
                $res->secondary_exposures,
            );
            return new DynamicConfig($config, $res->json_value, $res->rule_id, $res->secondary_exposures, $res->group_name);
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

    function getLayer(StatsigUser $user, string $layer): Layer
    {
        $task = function () use ($user, $layer) {
            $user = $this->normalizeUser($user);
            $res = $this->evaluator->getLayer($user, $layer);
            $json_value = $res->json_value;
            $rule_id = $res->rule_id;
            $log_exposure_fn = function ($parameter) use ($user, $layer, $rule_id, $res) {
                $this->logger->logLayerExposure(
                    $user,
                    $layer,
                    $rule_id,
                    $parameter,
                    $res,
                );
            };
            return new Layer($layer, $json_value, $rule_id, $log_exposure_fn, $res->group_name, $res->allocated_experiment == "" ? null : $res->allocated_experiment);
        };
        $fallback = function () use ($layer) {
            return new Layer($layer);
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

    function getClientInitializeResponse(StatsigUser $user, ?string $client_sdk_key = null)
    {
        return $this->evaluator->getClientInitializeResponse($user, $client_sdk_key);
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
