<?php

namespace Statsig;

abstract class StatsigMetadata
{
    public const VERSION = "3.6.3";
    public const SDK_TYPE = "php-server";

    public static function getJson(): array
    {
        return [
            'sdkType' => self::SDK_TYPE,
            'sdkVersion' => self::VERSION,
            'languageVersion' => phpversion(),
        ];
    }

    public static function getJsonWithSessionID($session_id): array
    {
        return [
            'sdkType' => self::SDK_TYPE,
            'sdkVersion' => self::VERSION,
            'languageVersion' => phpversion(),
            'sessionID' => $session_id
        ];
    }
}
