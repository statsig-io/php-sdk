<?php

namespace Statsig;

use Statsig\Adapters\ILoggingAdapter;

class StatsigLogger
{
    private array $events = [];
    private int $event_queue_size;
    private StatsigNetwork $network;
    private ?ILoggingAdapter $logging_adapter;

    function __construct(StatsigOptions  $options, StatsigNetwork $net)
    {
        $this->network = $net;
        $this->logging_adapter = $options->getLoggingAdapter();
        $this->event_queue_size = $options->getEventQueueSize();
    }

    function log($event)
    {
        $this->enqueue($event->toJson());
    }

    function logGateExposure(StatsigUser $user, string $gate, bool $bool_value, string $rule_id, array $secondary_exposures, ?EvaluationDetails $evaluation_details = null)
    {
        $exposure = new StatsigEvent("statsig::gate_exposure");
        $exposure->setUser($user);
        $exposure->setMetadata([
            "gate" => $gate,
            "gateValue" => $bool_value ? "true" : "false",
            "ruleID" => $rule_id,
            "reason" => $evaluation_details !== null ? $evaluation_details->reason : "",
            "initTime" => $evaluation_details !== null ? $evaluation_details->initTime : 0,
            "serverTime" => $evaluation_details !== null ? $evaluation_details->serverTime : 0,
            "configSyncTime" => $evaluation_details !== null ? $evaluation_details->configSyncTime : 0,
        ]);
        $json = $exposure->toJson();
        $json->{"secondaryExposures"} = $secondary_exposures;
        $this->enqueue($json);
    }

    function logConfigExposure(StatsigUser $user, string $config, string $rule_id, array $secondary_exposures, ?EvaluationDetails $evaluation_details = null)
    {
        $exposure = new StatsigEvent("statsig::config_exposure");
        $exposure->setUser($user);
        $exposure->setMetadata([
            "config" => $config,
            "ruleID" => $rule_id,
            "reason" => $evaluation_details !== null ? $evaluation_details->reason : "",
            "initTime" => $evaluation_details !== null ? $evaluation_details->initTime : 0,
            "serverTime" => $evaluation_details !== null ? $evaluation_details->serverTime : 0,
            "configSyncTime" => $evaluation_details !== null ? $evaluation_details->configSyncTime : 0,
        ]);
        $json = $exposure->toJson();
        $json->{"secondaryExposures"} = $secondary_exposures;
        $this->enqueue($json);
    }

    function logLayerExposure(StatsigUser $user, string $layer, string $rule_id, string $parameter, ConfigEvaluation $evaluation, ?EvaluationDetails $evaluation_details = null)
    {
        $exposure = new StatsigEvent("statsig::layer_exposure");
        $exposure->setUser($user);
        $is_explicit = in_array($parameter, $evaluation->explicit_parameters);
        $exposure->setMetadata([
            "config" => $layer,
            "ruleID" => $rule_id,
            "allocatedExperiment" => $is_explicit ? $evaluation->allocated_experiment : "",
            "parameterName" => $parameter,
            "isExplicitParameter" => $is_explicit ? "true" : "false",
            "reason" => $evaluation_details !== null ? $evaluation_details->reason : "",
            "initTime" => $evaluation_details !== null ? $evaluation_details->initTime : 0,
            "serverTime" => $evaluation_details !== null ? $evaluation_details->serverTime : 0,
            "configSyncTime" => $evaluation_details !== null ? $evaluation_details->configSyncTime : 0,
        ]);
        $json = $exposure->toJson();
        $secondary_exposures = $evaluation->undelegated_secondary_exposures;
        $json->{"secondaryExposures"} = $secondary_exposures ?? [];
        $this->enqueue($json);
    }

    function enqueue($json)
    {
        $this->events[] = $json;
        if (count($this->events) >= $this->event_queue_size) {
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
        } else {
            $this->network->logEvents($events);
        }
    }

    function shutdown() {
        $this->flush();

        if ($this->logging_adapter !== null) {
            $this->logging_adapter->shutdown();
        }
    }
}
