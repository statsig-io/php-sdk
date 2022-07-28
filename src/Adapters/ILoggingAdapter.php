<?php

namespace Statsig\Adapters;

interface ILoggingAdapter
{
    public function logEvents(array $events);
    public function open();
    public function close();
}