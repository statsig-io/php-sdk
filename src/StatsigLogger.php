<?php

namespace Statsig;

use Exception;

class StatsigLogger
{
    private array $events = [];
    private StatsigNetwork $network;
    private $logging_output_file;

    function __construct(StatsigOptions  $options, StatsigNetwork $net)
    {
        $this->network = $net;

        if ($options->getLoggingFilePath() == null) {
            $this->logging_output_file = null;
            return;
        }

        try {
            $file_name = $options->getLoggingFilePath();
            $open = @fopen($file_name, 'ab');
            if ($open !== false) {
                $this->logging_output_file = $open;
                chmod($file_name, 0644);
            }
        } catch (Exception $e) {
            $this->logging_output_file = null;
        }
    }

    public function __destruct()
    {
        if ($this->logging_output_file === null) {
            return;
        }
        $this->flush();
        fclose($this->logging_output_file);
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
        if ($this->logging_output_file !== null) {
            try {
                $content = json_encode($events);
                $content .= "\n";

                @fwrite($this->logging_output_file, $content);
            } catch (Exception $e) {
            }
        } else {
            $this->network->log_events($events);
        }
    }
}
