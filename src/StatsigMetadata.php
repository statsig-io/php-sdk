<?php

namespace Statsig;

abstract class StatsigMetadata
{
    public const VERSION = "3.5.1";
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
