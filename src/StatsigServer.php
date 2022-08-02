<?php

namespace Statsig;

class StatsigServer
{
    private StatsigNetwork $network;
    private Evaluator $evaluator;
    private StatsigLogger $logger;
    private StatsigOptions $options;
    private StatsigStore $store;

    function __construct(string $sdk_key, StatsigOptions $options)
    {
        $this->network = new StatsigNetwork();
        $this->network->setSdkKey($sdk_key);

        $this->store = new StatsigStore($this->network, $options->getConfigAdapter());
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
    }

    function getConfig(StatsigUser $user, string $config): DynamicConfig
    {
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
    }

    function getExperiment(StatsigUser $user, string $experiment): DynamicConfig
    {
        $user = $this->normalizeUser($user);
        return $this->getConfig($user, $experiment);
    }

    function logEvent(StatsigEvent $event)
    {
        if ($this->options->tier !== null) {
            $event->user['statsigEnvironment'] = ["tier" => $this->options->tier];
        }
        $this->logger->log($event);
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
