<?php

namespace Statsig;

abstract class StatsigMetadata
{
    public const VERSION = "2.4.0";
    public const SDK_TYPE = "php-server";

    public static function getJson(): array
    {
        return [
            'sdkType' => self::SDK_TYPE,
            'sdkVersion' => self::VERSION,
            'languageVersion' => phpversion()
        ];
    }
}
