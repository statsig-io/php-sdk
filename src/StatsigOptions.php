<?php

namespace Statsig;

use Statsig\Adapters\IConfigAdapter;
use Statsig\Adapters\ILoggingAdapter;

class StatsigOptions
{
    public ?string $tier = null;

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
}