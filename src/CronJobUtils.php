<?php

namespace Statsig;

use ReflectionClass;
use ReflectionException;

abstract class CronJobUtils
{
    public const VERSION = "0.3.1";

    /**
     * @param string $adapter_class The fully namespaced classname of the Adapter e.g. AVendor\Foo\MyAdapter
     * @param string|null $adapter_path The file path to the Adapter class on disk e.g. /path/to/MyAdapter.php
     * @param $adapter_args mixed An array of arguments to be passed to the Adapter class on initialization
     * @param $adapter_interface string The interface that the Adapter must implement e.g. MyAdapter::class
     * @return object|void
     */
    public static function getAdapter(string $adapter_class, ?string $adapter_path, $adapter_args, string $adapter_interface)
    {
        if (!empty($adapter_path)) {
            require_once $adapter_path;
        }

        $adapter_args = $adapter_args ?? [];
        $adapter_args = gettype($adapter_args) == 'array' ? $adapter_args : [$adapter_args];

        try {
            $reflector = new ReflectionClass($adapter_class);
            $adapter = $reflector->newInstanceArgs($adapter_args);
        } catch (ReflectionException $e) {
            die('Adapter failed to initialize');
        }

        if (!($adapter instanceof $adapter_interface)) {
            die('Adapter must implement ' . $adapter_interface);
        }

        return $adapter;
    }

    public static function getCommandLineArgs(): array
    {
        $long_options = ["secret:", "adapter-class:", "adapter-path:", "adapter-arg:"];
        return getopt("", $long_options);
    }
}
