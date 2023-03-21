<?php

namespace Statsig;

abstract class EvaluationUtils
{
    public static function matchStringInArray($val, $target, $compare): bool
    {
        $value = self::getValueAsString($val);
        if ($value == null) {
            return false;
        }
        if (!is_iterable($target)) {
            $target = [$target];
        }

        foreach ($target as $match) {
            $str_match = self::getValueAsString($match);
            if ($str_match == null) {
                continue;
            }
            if ($compare($value, $str_match)) {
                return true;
            }
        }
        return false;
    }

    public static function versionCompare($v1, $v2): int
    {
        $parts1 = explode('.', $v1);
        $parts2 = explode('.', $v2);

        $i = 0;
        while ($i < max(count($parts1), count($parts2))) {
            $c1 = 0;
            $c2 = 0;
            if ($i < count($parts1)) {
                $c1 = intval($parts1[$i]);
            }
            if ($i < count($parts2)) {
                $c2 = intval($parts2[$i]);
            }
            if ($c1 < $c2) {
                return -1;
            } else if ($c1 > $c2) {
                return 1;
            }
            $i++;
        }
        return 0;
    }

    public static function versionCompareHelper($v1, $v2, $compare)
    {
        $v1 = self::getValueAsString($v1);
        $v2 = self::getValueAsString($v2);

        if ($v1 === null || $v2 === null) {
            return false;
        }

        $dash_idx = strpos($v1, '-');
        if ($dash_idx > 0) {
            $v1 = substr($v1, 0, $dash_idx);
        }

        $dash_idx_2 = strpos($v2, '-');
        if ($dash_idx_2 > 0) {
            $v2 = substr($v2, 0, $dash_idx_2);
        }

        return $compare($v1, $v2);
    }

    public static function evalPassPercentage($user, $rule, $config): bool
    {
        $id_type = array_key_exists("idType", $rule) ? $rule["idType"] : "";
        $unit_id = self::getUnitID($user, $id_type) ?? "";
        $salted_id = sprintf(
            "%s.%s.%s",
            $config["salt"],
            $rule["salt"] ?? $rule["id"],
            $unit_id
        );
        $hash = self::computeUserHash($salted_id);
        return (
            // substr -4 == mod 10000
            intval(substr($hash, -4)) < ($rule["passPercentage"] * 100)
        );
    }

    public static function getUnitID($user, $id_type)
    {
        $lower_type = strtolower($id_type);
        if (strtolower($lower_type) !== "userid") {
            $custom_ids = $user["customIDs"] ?? [];
            if (empty($custom_ids)) {
                return null;
            }
            if (array_key_exists($id_type, $custom_ids)) {
                return $custom_ids[$id_type];
            } else if (array_key_exists($lower_type, $custom_ids)) {
                return $custom_ids[$lower_type];
            } else {
                return null;
            }
        }
        return $user["userID"] ?? null;
    }

    public static function getFromUser($user, $field)
    {
        $lower_field = strtolower($field);
        if (array_key_exists($field, $user)) {
            return $user[$field];
        }
        if (array_key_exists($lower_field, $user)) {
            return $user[$lower_field];
        }

        if (array_key_exists("custom", $user)) {
            $c = $user["custom"];
            if (array_key_exists($field, $c)) {
                return $c[$field];
            }
            if (array_key_exists($lower_field, $c)) {
                return $c[$lower_field];
            }
        }

        if (array_key_exists("privateAttributes", $user)) {
            $p = $user["privateAttributes"];
            if (array_key_exists($field, $p)) {
                return $p[$field];
            }
            if (array_key_exists($lower_field, $p)) {
                return $p[$lower_field];
            }
        }
        return null;
    }


    public static function getFromEnvironment($user, $field)
    {
        $lower_field = strtolower($field);

        if (array_key_exists("statsigEnvironment", $user)) {
            $env = $user["statsigEnvironment"];
            if (array_key_exists($field, $env)) {
                return $env[$field];
            }
            if (array_key_exists($lower_field, $env)) {
                return $env[$lower_field];
            }
        }
        return null;
    }

    public static function getValueAsString($value): ?string
    {
        return $value === null ? null : strval($value);
    }

    public static function getValueAsFloat($value): ?float
    {
        return $value === null ? null : floatval($value);
    }

    public static function computeUserHash($val): string
    {
        $hash = hash("sha256", $val, True);
        $hex = bin2hex(substr($hash, 0, 8));
        return self::baseConvertToString($hex, 16, 10);
    }

    /**
     * We can't use the built in base_convert because it loses precision on large numbers
     * https://www.php.net/manual/en/function.base-convert.php
     *
     * So, our options become either to 1) require gmp, or 2) roll our own way
     * we just need the last few digits to determine bucketing, so creating a string
     * and then taking the substring down to the digits we need is enough
     * we only need up to the 10000 digit, which can then be represented as an integer again
     * Implemenetation from: https://stackoverflow.com/questions/5301034/how-to-generate-random-64-bit-value-as-decimal-string-in-php/5302533#5302533
     */
    public static function baseConvertToString($number, $from_base, $to_base): string
    {
        $digits = '0123456789abcdefghijklmnopqrstuvwxyz';
        $length = strlen($number);
        $result = '';

        $nibbles = array();
        for ($i = 0; $i < $length; ++$i) {
            $nibbles[$i] = strpos($digits, $number[$i]);
        }

        do {
            $value = 0;
            $newlen = 0;
            for ($i = 0; $i < $length; ++$i) {
                $value = $value * $from_base + $nibbles[$i];
                if ($value >= $to_base) {
                    $nibbles[$newlen++] = (int)($value / $to_base);
                    $value %= $to_base;
                } else if ($newlen > 0) {
                    $nibbles[$newlen++] = 0;
                }
            }
            $length = $newlen;
            $result = $digits[$value] . $result;
        } while ($newlen != 0);
        return $result;
    }

    public static function stringStartsWith($haystack, $needle): bool
    {
        $length = strlen($needle);
        return substr($haystack, 0, $length) === $needle;
    }

    public static function stringEndsWith($haystack, $needle): bool
    {
        $length = strlen($needle);
        if (!$length) {
            return true;
        }
        return substr($haystack, -$length) === $needle;
    }
}
