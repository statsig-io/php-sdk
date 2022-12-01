<?php

namespace Statsig;

abstract class StatsigMetadata
{
    public const VERSION = "2.0.0";
    public const SDK_TYPE = "php-server";

    public static function getJson(): array
    {
        return [
            'sdkType' => self::SDK_TYPE,
            'sdkVersion' => self::VERSION
        ];
    }
}
