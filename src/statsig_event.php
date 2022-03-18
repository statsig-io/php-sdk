<?php

namespace Statsig;

class StatsigEvent {
    private $eventName;
    private $user;
    private $value;
    private $metadata;

    function __construct($name) {
        $this->eventName = $name;
        $this->time = round(microtime(true) * 1000);
    }

    function setUser($user) {
        $this->user = $user->toLogDictionary();
    }

    function setValue($value) {
        $this->value = $value;
    }

    function setMetadata($metadata) {
        $this->metadata = $metadata;
    }

    function setTime($time) {
        $this->time = $time;
    }

    function toJson() {
        $evt = (object)[];
        $evt->eventName = $this->eventName;
        $evt->user = $this->user;
        $evt->value = $this->value;
        $evt->metadata = $this->metadata;
        $evt->time = $this->time;
        return $evt;
    }

}