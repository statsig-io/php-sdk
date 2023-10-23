<?php

namespace Statsig;

class HashingUtils
{
    public static function djb2(string $input): string
    {
        $hash = 0;
        for ($i = 0; $i < strlen($input); $i++) {
            $hash = (($hash << 5) - $hash) + ord($input[$i]);
            $hash = $hash & ((1<<32) - 1);
        }
        return $hash;
    }

    public static function hashArray(array $input): string 
    {
        $json = json_encode(self::sortNestedArray($input), JSON_FORCE_OBJECT);
        return self::djb2($json);
    }

    public static function sortNestedArray(array $input): array
    {
        ksort($input);
        foreach ($input as $key => $value) {
            if (is_array($value)) {
                $input[$key] = self::sortNestedArray($value);
            }
        }
        return $input;
    }
}