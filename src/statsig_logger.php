<?php

namespace Statsig;

class StatsigLogger {
    private $events = [];
    private $file;

    function __construct($options) {
        $fileName = $options->getLogOutputFile();
        $this->file = @fopen($fileName, 'ab');
        chmod($fileName, 0644);
    }

    public function __destruct() {
        if ($this->file === null) {
            return;
        }
        $this->flush();
        fclose($this->file);
    }

    function log($event) {
        $this->enqueue($event->toJson());
    }

    function logGateExposure($user, $gate, $boolValue, $ruleID, $secondaryExposures) {
        $exposure = new StatsigEvent("statsig::gate_exposure");
        $exposure->setUser($user);
        $exposure->setMetadata([
            "gate" => $gate,
            "gateValue" => $boolValue ? "true" : "false",
            "ruleID" => $ruleID,
        ]);
        $json = $exposure->toJson();
        $json->{"secondaryExposures"} = $secondaryExposures;
        $this->enqueue($json);
    }

    function logConfigExposure($user, $config, $ruleID, $secondaryExposures) {
        $exposure = new StatsigEvent("statsig::config_exposure");
        $exposure->setUser($user);
        $exposure->setMetadata([
            "config" => $config,
            "ruleID" => $ruleID,
        ]);
        $json = $exposure->toJson();
        $json->{"secondaryExposures"} = $secondaryExposures;
        $this->enqueue($json);
    }

    function enqueue($json) {
        $this->events[] = $json;
        if (count($this->events) >= 500) {
            $this->flush();
        }
    }

    function flush() {
        if (count($this->events) == 0) {
            return;
        }
        $events = $this->events;
        $this->events = [];
        if ($this->file !== null) {
            $content = json_encode($events);
            $content .= "\n";

            fwrite($this->file, $content);
            return;
        }
        $this->network->log_events($events);
    }

}