<?php

namespace Statsig\Adapters;

interface ILoggingAdapter
{
    public function enqueueEvents(array $events);
    public function getQueuedEvents(): array;
    public function shutdown();
}