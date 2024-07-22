<?php

namespace Statsig;

class EvaluationDetails
{
    public string $reason;
    public int $initTime;
    public int $serverTime;
    public int $configSyncTime;

    function __construct(string $reason, int $initTime, int $configSyncTime)
    {
        $this->reason = $reason;
        $this->initTime = $initTime;
        $this->serverTime = floor(microtime(true) * 1000);
        $this->configSyncTime = $configSyncTime;
    }
}