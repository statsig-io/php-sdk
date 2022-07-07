<?php

namespace Statsig;

use UAParser\Parser;
use ip3country\IP3Country;
use Statsig\ConfigEvaluation;

class Evaluator {
    private $store;
    private $network;
    private $uaParser;
    private $ip3c;

    function __construct($options) {
        $this->store = new StatsigStore($options->getConfigFile());
        $this->uaParser = Parser::create();
        $this->ip3c = new IP3Country();
    }

    function checkGate($user, $gate) {
        $def = $this->store->getGateDefinition($gate);
        if ($def === null) {
            return new ConfigEvaluation(false, "");
        }
        return $this->eval($user, $def);
    }

    function getConfig($user, $config) {
        $def = $this->store->getConfigDefinition($config);
        if ($def === null) {
            return new ConfigEvaluation(false, "");
        }
        return $this->eval($user, $def);
    }

    function eval($user, $config) {
        if (!$config["enabled"]) {
            return new ConfigEvaluation(false, "disabled", $config["defaultValue"]);
        }
        $secondary_exposures = [];
        for ($i = 0; $i < count($config["rules"]); $i++) {
            $rule = $config["rules"][$i];
            $ruleResult = $this->evalRule($user, $rule);
            if ($ruleResult->fetchFromServer) {
                return $ruleResult;
            }
            $secondary_exposures = array_merge($secondary_exposures, $ruleResult->secondaryExposures);
            if ($ruleResult->boolValue === true) {
                $pass = $this->evalPassPercentage($user->toEvaluationDictionary(), $rule, $config);
                return new ConfigEvaluation($pass === true, $rule["id"], $pass === true ? $rule["returnValue"] : $config["defaultValue"], $secondary_exposures);
            }
        }
        return new ConfigEvaluation(false, "default", $config["defaultValue"], $secondary_exposures);
    }

    function evalRule($user, $rule) {
        $secondary_exposures = [];
        $condition_results = [];
        for ($i = 0; $i < count($rule["conditions"]); $i++) {
            $condition = $rule["conditions"][$i];
            $condition_result = $this->evalCondition($user, $condition);
            if ($condition_result->fetchFromServer) {
                return $condition_result;
            }
            $condition_results[] = $condition_result;
        }
        $result = new ConfigEvaluation(true, $rule["id"], $rule["returnValue"], $secondary_exposures);

        for ($i = 0; $i < count($condition_results); $i++) {
            $condition_result = $condition_results[$i];
            if ($condition_result->fetchFromServer) {
                $result->fetchFromServer = true;
            }
            if ($condition_result->boolValue === false) {
                $result->boolValue = false;
            }
            $result->secondaryExposures = array_merge($result->secondaryExposures, $condition_result->secondaryExposures);
        }
        return $result;
    }

    function evalCondition($user_obj, $condition) {
        $user = $user_obj->toEvaluationDictionary();
        $value = null;

        $field = array_key_exists("field", $condition) ? $condition["field"] : null;
        $target = array_key_exists("targetValue", $condition) ? $condition["targetValue"] : null;
        $idType = array_key_exists("idType", $condition) ? $condition["idType"] : null;
        $type = array_key_exists("type", $condition) ? $condition["type"] : null;

        switch (strtolower($type)) {
            case 'public': 
                return new ConfigEvaluation(true, "");
            case 'fail_gate':
            case 'pass_gate':
                $nested = $this->checkGate($user_obj, $target);
                $result = strtolower($type) === 'pass_gate'
                    ? $nested->boolValue
                    : !$nested->boolValue;
                $all_exposures = $nested->secondaryExposures;
                array_push($all_exposures, [
                    "gate" => $target,
                    "gateValue" => $nested->boolValue ? "true" : "false",
                    "ruleID" => $nested->ruleID,
                ]);
                return new ConfigEvaluation($result, "", $nested->jsonValue, $all_exposures, $nested->fetchFromServer);
            case 'ip_based':
                $value = $this->getFromUser($user, $field) ?? $this->getFromIP($user, $field);
                break;
            case 'ua_based':
                $value = $this->getFromUser($user, $field) ?? $this->getFromUserAgent($user, $field);
                break;
            case 'user_field':
                $value = $this->getFromUser($user, $field);
                break;
            case 'current_time':
                $value = time() * 1000;
                break;
            case 'environment_field':
                $value = $this->getFromEnvironment($user, $field);
                break;
            case 'user_bucket':
                $additional_values = array_key_exists("additionalValues", $condition) ? $condition["additionalValues"] : array();
                $salt = array_key_exists("salt", $additional_values) ? $additional_values["salt"] : "";
                $salt = $this->getValueAsString($salt);
                $unit_id = $this->getUnitID($user, $idType);
                $hash = $this->computeUserHash(sprintf("%s.%s", $salt, $unit_id));
                // substr -3 == mod 1000
                $value = intval(substr($hash, -3));
                break;
            case 'unit_id':
                $value = $this->getUnitID($user, $idType);
                break;
            default:
                return new ConfigEvaluation(false, "", [], [], true);
        }


        $operator = array_key_exists("operator", $condition) ? $condition["operator"] : "";

        switch ($operator) {
            case 'gt': {
                $floatVal = $this->getValueAsFloat($value);
                $floatTarget = $this->getValueAsFloat($target);
                if ($floatVal === null || $floatTarget === null) {
                    return new ConfigEvaluation(false);
                }
                return new ConfigEvaluation($floatVal > $floatTarget);
            }
            case 'gte': {
                $floatVal = $this->getValueAsFloat($value);
                $floatTarget = $this->getValueAsFloat($target);
                if ($floatVal === null || $floatTarget === null) {
                    return new ConfigEvaluation(false);
                }
                return new ConfigEvaluation($floatVal >= $floatTarget);
            }
            case 'lt': {
                $floatVal = $this->getValueAsFloat($value);
                $floatTarget = $this->getValueAsFloat($target);
                if ($floatVal === null || $floatTarget === null) {
                    return new ConfigEvaluation(false);
                }
                return new ConfigEvaluation($floatVal < $floatTarget);
            }
            case 'lte': {
                $floatVal = $this->getValueAsFloat($value);
                $floatTarget = $this->getValueAsFloat($target);
                if ($floatVal === null || $floatTarget === null) {
                    return new ConfigEvaluation(false);
                }
                return new ConfigEvaluation($floatVal <= $floatTarget);
            }
            case 'version_gt': {
                return new ConfigEvaluation(
                    $this->versionCompareHelper($value, $target, function($v1, $v2) {
                        return $this->versionCompare($v1, $v2) > 0;
                    })
                );
            }
            case 'version_gte': {
                return new ConfigEvaluation(
                    $this->versionCompareHelper($value, $target, function($v1, $v2) {
                        return $this->versionCompare($v1, $v2) >= 0;
                    })
                );
            }
            case 'version_lt': {
                return new ConfigEvaluation(
                    $this->versionCompareHelper($value, $target, function($v1, $v2) {
                        return $this->versionCompare($v1, $v2) < 0;
                    })
                );
            }
            case 'version_lte': {
                return new ConfigEvaluation(
                    $this->versionCompareHelper($value, $target, function($v1, $v2) {
                        return $this->versionCompare($v1, $v2) <= 0;
                    })
                );
            }
            case 'version_eq': {
                return new ConfigEvaluation(
                    $this->versionCompareHelper($value, $target, function($v1, $v2) {
                        return $this->versionCompare($v1, $v2) === 0;
                    })
                );
            }
            case 'version_neq': {
                return new ConfigEvaluation(
                    $this->versionCompareHelper($value, $target, function($v1, $v2) {
                        return $this->versionCompare($v1, $v2) !== 0;
                    })
                );
            }
            case 'any': {
                return new ConfigEvaluation(
                    $this->matchStringInArray($value, $target, function($a, $b) {
                        return strcasecmp($a, $b) === 0;
                    })
                );
            }
            case 'none': {
                return new ConfigEvaluation(
                    !$this->matchStringInArray($value, $target, function($a, $b) {
                        return strcasecmp($a, $b) === 0;
                    })
                );
            }
            case 'any_case_sensitive': {
                return new ConfigEvaluation(
                    $this->matchStringInArray($value, $target, function($a, $b) {
                        return strcmp($a, $b) === 0;
                    })
                );
            }
            case 'none_case_sensitive': {
                return new ConfigEvaluation(
                    !$this->matchStringInArray($value, $target, function($a, $b) {
                        return strcmp($a, $b) === 0;
                    })
                );
            }
            case 'str_starts_with_any': {
                return new ConfigEvaluation(
                    $this->matchStringInArray($value, $target, function($a, $b) {
                        return str_starts_with($a, $b);
                    })
                );
            }
            case 'str_ends_with_any': {
                return new ConfigEvaluation(
                    $this->matchStringInArray($value, $target, function($a, $b) {
                        return str_ends_with($a, $b);
                    })
                );
            }
            case 'str_contains_any': {
                return new ConfigEvaluation(
                    $this->matchStringInArray($value, $target, function($a, $b) {
                        return stripos($a, $b) !== false;
                    })
                );
            }
            case 'str_contains_none': {
                return new ConfigEvaluation(
                    !$this->matchStringInArray($value, $target, function($a, $b) {
                        return stripos($a, $b) !== false;
                    })
                );
            }
            case 'str_matches': {
                $str_val = $this->getValueAsString($value);
                if ($str_val === null) {
                    return new ConfigEvaluation(false);
                }

                return new ConfigEvaluation(
                    preg_match($target, $str_val)
                );
            }
            case 'eq':
                return new ConfigEvaluation($value === $target);
            case 'neq':
                return new ConfigEvaluation($value !== $target);
            case 'before':
                return new ConfigEvaluation(date($value) < date($target));
            case 'after':
                return new ConfigEvaluation(date($value) > date($target));
            case 'on':
                $a = date($value)->format('Y-m-d');
                $b = date($target)->format('Y-m-d');
                return new ConfigEvaluation($a == $b);
            case 'in_segment_list':
            case 'not_in_segment_list':
                // TODO id lists
                return new ConfigEvaluation(false, "", [], [], true);
            default:
                return new ConfigEvaluation(false, "", [], [], true);
        }
    }

    function matchStringInArray($val, $target, $compare) {
        $value = $this->getValueAsString($val);
        if ($value == null) {
            return false;
        }
        if (!is_iterable($target)) {
            $target = [$target];
        }

        foreach ($target as $match) {
            $str_match = $this->getValueAsString($match);
            if ($str_match == null) {
                continue;
            }
            if ($compare($value, $str_match)) {
                return true;
            }
        }
        return false;
    }

    function versionCompare($v1, $v2) {
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

    function versionCompareHelper($v1, $v2, $compare) {
        $v1 = $this->getValueAsString($v1);
        $v2 = $this->getValueAsString($v2);

        if ($v1 === null || $v1 === null) {
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

    function evalPassPercentage($user, $rule, $config) {
        $id_type = array_key_exists("idType", $rule) ? $rule["idType"] : "";
        $unit_id = $this->getUnitID($user, $id_type) ?? "";
        $salted_id = sprintf(
            "%s.%s.%s",
            $config["salt"],
            $rule["salt"] ?? $rule["id"],
            $unit_id
        );
        $hash = $this->computeUserHash($salted_id);
        return (
            // substr -4 == mod 10000
            intval(substr($hash, -4)) < ($rule["passPercentage"] * 100)
        );
    }

    function getUnitID($user, $id_type) {
        $lower_type = strtolower($id_type);
        if (strtolower($lower_type) !== "userid") {
            $custom_ids = $user["customIDs"] ?? [];
            if (empty($custom_ids)) {
                return '';
            }
            if (array_key_exists($id_type, $custom_ids)) {
                return $custom_ids[$id_type];
            } else if (array_key_exists($lower_type, $custom_ids)) {
                return $custom_ids[$lower_type];
            } else {
                return '';
            }
        }
        return $user["userID"];
    }

    function getFromUser($user, $field) {
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

    function getFromIP($user, $field) {
        $ip = $this->getFromUser($user, "ip");
        if ($ip === null || $field != "country") {
            return null;
        }
        return $this->ip3c->lookup($ip);
    }

    function getFromUserAgent($user, $field) {
        $ua = $this->getFromUser($user, "userAgent");
        if ($ua == null) {
            return null;
        }
        if (!is_string($ua) || strlen($ua) > 1000) {
            return null;
        }

        $res = $this->uaParser->parse($ua);
        switch(strtolower($field)) {
            case 'os_name':
            case 'osname':
                return $res->os->family ?? null;
            case 'os_version':
            case 'osversion':
                return $res->os->toVersion() ?? null;
            case 'browser_name':
            case 'browsername':
                return $res->ua->family ?? null;
            case 'browser_version':
            case 'browserversion':
                return $res->ua->toVersion() ?? null;
            default:
                return null;
        }
    }

    function getFromEnvironment($user, $field) {
        if (array_key_exists("statsigEnvironment", $user)) {
            $env = $user["statsigEnvironment"];
            if (array_key_exists($field, $env)) {
                return $env[$field];
            }
            if (array_key_exists($lower_field, $env)) {
                return $env[strtolower($field)];
            }
        }
        return null;
    }

    function getValueAsString($value) {
        return $value === null ? null : strval($value);
    }

    function getValueAsFloat($value) {
        return $value === null ? null : floatval($value);
    }

    function computeUserHash($val) {
        $hash = hash("sha256", $val, True); 
        $hex = bin2hex(substr($hash, 0, 8));
        
        return $this->base_convert_to_string($hex, 16, 10);
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
    function base_convert_to_string($number, $fromBase, $toBase) {
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
                $value = $value * $fromBase + $nibbles[$i];
                if ($value >= $toBase) {
                    $nibbles[$newlen++] = (int)($value / $toBase);
                    $value %= $toBase;
                }
                else if ($newlen > 0) {
                    $nibbles[$newlen++] = 0;
                }
            }
            $length = $newlen;
            $result = $digits[$value].$result;
        }
        while ($newlen != 0);
        return $result;
    }
}
