<?php

namespace Statsig;

use ReflectionClass;
use ReflectionException;

abstract class CronJobUtils
{
    public static function getAdapter(string $adapter_name, $adapter_args, $adapter_interface)
    {
        $adapter_args =$adapter_args ?? [];
        $adapter_args = gettype($adapter_args) == 'array' ? $adapter_args : [$adapter_args];

        $adapter = null;
        try {
            $adapter_reflection = new ReflectionClass($adapter_name);
            $adapter = $adapter_reflection->newInstanceArgs($adapter_args);
        } catch (ReflectionException $e) {
            die('--adapter failed to initialize');
        }

        if (!($adapter instanceof $adapter_interface)) {
            die('--adapter must implement ' . $adapter_interface);
        }

        return $adapter;
    }
}