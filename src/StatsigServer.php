<?php

namespace Statsig;

use Statsig\StatsigNetwork;
use Statsig\Evaluator;
use Statsig\StatsigLogger;
use Statsig\StatsigEvent;
use Statsig\DynamicConfig;

class StatsigServer {

    private $network;
    private $logger;
    private $evaluator;
    private $options;

    function __construct($sdk_key, $options) {
        $this->evaluator = new Evaluator($options);
        $this->network = new StatsigNetwork();
        $this->network->setSdkKey($sdk_key);

        $this->logger = new StatsigLogger($options, $this->network);
        $this->options = $options;
    }

    function __destruct() {
        $this->logger->flush();
    }

    function checkGate($user, $gate) {
        $user = $this->normalizeUser($user);
        $res = $this->evaluator->checkGate($user, $gate);
        if ($res->fetchFromServer) {
            $net_result = $this->network->checkGate($user, $gate);
            return $net_result["value"];
        }
        $this->logger->logGateExposure(
            $user,
            $gate,
            $res->boolValue,
            $res->ruleID,
            $res->secondaryExposures,
        );
        return $res->boolValue;
    }

    function getConfig($user, $config) {
        $user = $this->normalizeUser($user);
        $res = $this->evaluator->getConfig($user, $config);
        if ($res->fetchFromServer) {
            $net_result = $this->network->getConfig($user, $config);
            return new DynamicConfig($config, $net_result["value"], $net_result["rule_id"]);
        }
        $this->logger->logConfigExposure(
            $user,
            $config,
            $res->ruleID,
            $res->secondaryExposures,
        );
        return new DynamicConfig($config, $res->jsonValue, $res->ruleID);
    }

    function getExperiment($user, $experiment) {
        $user = $this->normalizeUser($user);
        return $this->getConfig($user, $experiment);
    }

    function logEvent($event) {
        if ($this->options->tier !== null) {
            $event->user["statsigEnvironment"] = ["tier" => $this->options->tier];
        }
        $this->logger->log($event);
    }

    function flush() {
        $this->logger->flush();
    }

    function normalizeUser($user) {
        $new_user;
        if ($user == null) {
            $new_user = StatsigUser();
        } else {
            $new_user = clone $user;
        }
        
        if ($this->options->tier !== null) {
            $new_user->setStatsigEnvironment(["tier" => $this->options->tier]);
        }
        return $new_user;
    }
}