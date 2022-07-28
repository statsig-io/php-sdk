<?php

namespace Statsig;

class StatsigEvent
{
    public ?array $user = null;

    private string $event_name;
    private int $time;
    private /* string | int */ $value = null;
    private ?array $metadata = null;

    function __construct($name)
    {
        $this->event_name = $name;
        $this->time = round(microtime(true) * 1000);
    }

    function setUser(StatsigUser $user)
    {
        $this->user = $user->toLogDictionary();
    }

    function setValue($value)
    {
        $this->value = $value;
    }

    function setMetadata($metadata)
    {
        $this->metadata = $metadata;
    }

    function setTime($time)
    {
        $this->time = $time;
    }

    function toJson(): object
    {
        $evt = (object)[];
        $evt->eventName = $this->event_name;
        $evt->user = $this->user;
        $evt->value = $this->value;
        $evt->metadata = $this->metadata;
        $evt->time = $this->time;
        return $evt;
    }
}
