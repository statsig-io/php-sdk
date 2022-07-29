<?php

namespace Statsig;

use Exception;
use Statsig\Adapters\ILoggingAdapter;

class StatsigLogger
{
    private array $events = [];
    private StatsigNetwork $network;
    private ?ILoggingAdapter $logging_adapter;

    function __construct(StatsigOptions  $options, StatsigNetwork $net)
    {
        $this->network = $net;

        $this->logging_adapter = $options->getLoggingAdapter();
        if ($this->logging_adapter !== null) {
            $this->logging_adapter->open();
        }
    }

    public function __destruct()
    {
        $this->flush();

        if ($this->logging_adapter !== null) {
            $this->logging_adapter->close();
        }
    }

    function log($event)
    {
        $this->enqueue($event->toJson());
    }

    function logGateExposure(StatsigUser $user, string $gate, bool $bool_value, string $rule_id, array $secondary_exposures)
    {
        $exposure = new StatsigEvent("statsig::gate_exposure");
        $exposure->setUser($user);
        $exposure->setMetadata([
            "gate" => $gate,
            "gateValue" => $bool_value ? "true" : "false",
            "ruleID" => $rule_id,
        ]);
        $json = $exposure->toJson();
        $json->{"secondaryExposures"} = $secondary_exposures;
        $this->enqueue($json);
    }

    function logConfigExposure(StatsigUser $user, string $config, string $rule_id, array $secondary_exposures)
    {
        $exposure = new StatsigEvent("statsig::config_exposure");
        $exposure->setUser($user);
        $exposure->setMetadata([
            "config" => $config,
            "ruleID" => $rule_id,
        ]);
        $json = $exposure->toJson();
        $json->{"secondaryExposures"} = $secondary_exposures;
        $this->enqueue($json);
    }

    function enqueue($json)
    {
        $this->events[] = $json;
        if (count($this->events) >= 500) {
            $this->flush();
        }
    }

    function flush()
    {
        if (count($this->events) == 0) {
            return;
        }
        $events = $this->events;
        $this->events = [];
        if ($this->logging_adapter !== null) {
            $this->logging_adapter->enqueueEvents($events);
        }  else {
            $this->network->logEvents($events);
        }
    }
}
