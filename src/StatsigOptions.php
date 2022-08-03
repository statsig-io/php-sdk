<?php

namespace Statsig;

use Statsig\Adapters\IConfigAdapter;
use Statsig\Adapters\ILoggingAdapter;
use Exception;

class StatsigOptions
{
    public ?string $tier = null;

    private int $event_queue_size = 500;
    private IConfigAdapter $config_adapter;
    private ?ILoggingAdapter $logging_adapter;

    function __construct(IConfigAdapter $config_adapter, ?ILoggingAdapter $logging_adapter = null)
    {
        $this->config_adapter = $config_adapter;
        $this->logging_adapter = $logging_adapter;
    }

    function getConfigAdapter(): IConfigAdapter
    {
        return $this->config_adapter;
    }

    function getLoggingAdapter(): ?ILoggingAdapter
    {
        return $this->logging_adapter;
    }

    function setEnvironmentTier(?string $tier)
    {
        $this->tier = $tier;
    }

    /**
     * @throws Exception - Given size cannot be less than 10 or greater than 1000
     */
    function setEventQueueSize(int $size)
    {
        if ($size < 10 || $size > 1000) {
            throw new Exception("Given size cannot be less than 10 or greater than 1000");
        }
        $this->event_queue_size = $size;
    }

    function getEventQueueSize(): int
    {
        return $this->event_queue_size;
    }
}