<?php

namespace Statsig;

require __DIR__.'/evaluator.php';
require __DIR__.'/statsig_logger.php';
require __DIR__.'/statsig_event.php';
require __DIR__.'/dynamic_config.php';
require __DIR__.'/statsig_network.php';

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
        $this->logger = new StatsigLogger($options);
        $this->network = new StatsigNetwork();
        $this->network->setSdkKey($sdk_key);
    }

    function __destruct() {
        $this->logger->flush();
    }

    function checkGate($user, $gate) {
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

    function logEvent($event) {
        $this->logger->log($event);
    }

    function getExperiment($user, $experiment) {
        return $this->getConfig($user, $experiment);
    }

    function flush() {
        $this->logger->flush();
    }
}