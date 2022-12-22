<?php

namespace Statsig;

use Statsig\Adapters\IDataAdapter;
use Statsig\Adapters\ILoggingAdapter;
use Statsig\Exceptions\EventQueueSizeException;
use Exception;

class StatsigOptions
{
    public ?string $tier = null;

    /**
     * @var int How old (in milliseconds) are config definitions allowed to be before a network request to refresh them is made.
     * default: 1 minute
     */
    public int $config_freshness_threshold_ms = 1000 * 60;

    private int $event_queue_size = 500;
    private IDataAdapter $data_adapter;
    private ?ILoggingAdapter $logging_adapter;

    function __construct(IDataAdapter $data_adapter, ?ILoggingAdapter $logging_adapter = null)
    {
        $this->data_adapter = $data_adapter;
        $this->logging_adapter = $logging_adapter;
    }

    function getDataAdapter(): IDataAdapter
    {
        return $this->data_adapter;
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
            throw new EventQueueSizeException("Given size cannot be less than 10 or greater than 1000");
        }
        $this->event_queue_size = $size;
    }

    function getEventQueueSize(): int
    {
        return $this->event_queue_size;
    }
}
