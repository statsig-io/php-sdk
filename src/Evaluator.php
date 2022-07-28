<?php

namespace Statsig;

use UAParser\Exception\FileNotFoundException;
use UAParser\Parser;
use ip3country\IP3Country;

use Statsig\EvaluationUtils as Utils;

class Evaluator
{
    private StatsigStore $store;
    private Parser $ua_parser;
    private IP3Country $ip3c;

    function __construct(StatsigOptions $options)
    {
        $this->store = new StatsigStore($options->getConfigFilePath());
        $this->ua_parser = Parser::create();
        $this->ip3c = new IP3Country();
    }

    function checkGate($user, $gate): ConfigEvaluation
    {
        $def = $this->store->getGateDefinition($gate);
        if ($def === null) {
            return new ConfigEvaluation(false, "");
        }
        return $this->eval($user, $def);
    }

    function getConfig($user, $config): ConfigEvaluation
    {
        $def = $this->store->getConfigDefinition($config);
        if ($def === null) {
            return new ConfigEvaluation(false, "");
        }
        return $this->eval($user, $def);
    }

    function eval($user, $config): ConfigEvaluation
    {
        if (!$config["enabled"]) {
            return new ConfigEvaluation(false, "disabled", $config["defaultValue"]);
        }
        $secondary_exposures = [];
        for ($i = 0; $i < count($config["rules"]); $i++) {
            $rule = $config["rules"][$i];
            $rule_result = $this->evalRule($user, $rule);
            if ($rule_result->fetch_from_server) {
                return $rule_result;
            }
            $secondary_exposures = array_merge($secondary_exposures, $rule_result->secondary_exposures);
            if ($rule_result->bool_value === true) {
                $pass = Utils::evalPassPercentage($user->toEvaluationDictionary(), $rule, $config);
                return new ConfigEvaluation($pass === true, $rule["id"], $pass === true ? $rule["returnValue"] : $config["defaultValue"], $secondary_exposures);
            }
        }
        return new ConfigEvaluation(false, "default", $config["defaultValue"], $secondary_exposures);
    }

    function evalRule($user, $rule): ConfigEvaluation
    {
        $secondary_exposures = [];
        $condition_results = [];
        for ($i = 0; $i < count($rule["conditions"]); $i++) {
            $condition = $rule["conditions"][$i];
            $condition_result = $this->evalCondition($user, $condition);
            if ($condition_result->fetch_from_server) {
                return $condition_result;
            }
            $condition_results[] = $condition_result;
        }
        $result = new ConfigEvaluation(true, $rule["id"], $rule["returnValue"], $secondary_exposures);

        for ($i = 0; $i < count($condition_results); $i++) {
            $condition_result = $condition_results[$i];
            if ($condition_result->fetch_from_server) {
                $result->fetch_from_server = true;
            }
            if ($condition_result->bool_value === false) {
                $result->bool_value = false;
            }
            $result->secondary_exposures = array_merge($result->secondary_exposures, $condition_result->secondary_exposures);
        }
        return $result;
    }

    function evalCondition($user_obj, $condition): ConfigEvaluation
    {
        $user = $user_obj->toEvaluationDictionary();

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
                    ? $nested->bool_value
                    : !$nested->bool_value;
                $all_exposures = $nested->secondary_exposures;
                $all_exposures[] = [
                    "gate" => $target,
                    "gateValue" => $nested->bool_value ? "true" : "false",
                    "ruleID" => $nested->rule_id,
                ];
                return new ConfigEvaluation($result, "", $nested->json_value, $all_exposures, $nested->fetch_from_server);
            case 'ip_based':
                $value = Utils::getFromUser($user, $field) ?? $this->getFromIP($user, $field);
                break;
            case 'ua_based':
                $value = Utils::getFromUser($user, $field) ?? $this->getFromUserAgent($user, $field);
                break;
            case 'user_field':
                $value = Utils::getFromUser($user, $field);
                break;
            case 'current_time':
                $value = time() * 1000;
                break;
            case 'environment_field':
                $value = Utils::getFromEnvironment($user, $field);
                break;
            case 'user_bucket':
                $additional_values = array_key_exists("additionalValues", $condition) ? $condition["additionalValues"] : array();
                $salt = array_key_exists("salt", $additional_values) ? $additional_values["salt"] : "";
                $salt = Utils::getValueAsString($salt);
                $unit_id = Utils::getUnitID($user, $idType);
                $hash = Utils::computeUserHash(sprintf("%s.%s", $salt, $unit_id));
                // substr -3 == mod 1000
                $value = intval(substr($hash, -3));
                break;
            case 'unit_id':
                $value = Utils::getUnitID($user, $idType);
                break;
            default:
                return new ConfigEvaluation(false, "", [], [], true);
        }


        $operator = array_key_exists("operator", $condition) ? $condition["operator"] : "";

        switch ($operator) {
            case 'gt': {
                    $float_val = Utils::getValueAsFloat($value);
                    $float_target = Utils::getValueAsFloat($target);
                    if ($float_val === null || $float_target === null) {
                        return new ConfigEvaluation(false);
                    }
                    return new ConfigEvaluation($float_val > $float_target);
                }
            case 'gte': {
                    $float_val = Utils::getValueAsFloat($value);
                    $float_target = Utils::getValueAsFloat($target);
                    if ($float_val === null || $float_target === null) {
                        return new ConfigEvaluation(false);
                    }
                    return new ConfigEvaluation($float_val >= $float_target);
                }
            case 'lt': {
                    $float_val = Utils::getValueAsFloat($value);
                    $float_target = Utils::getValueAsFloat($target);
                    if ($float_val === null || $float_target === null) {
                        return new ConfigEvaluation(false);
                    }
                    return new ConfigEvaluation($float_val < $float_target);
                }
            case 'lte': {
                    $float_val = Utils::getValueAsFloat($value);
                    $float_target = Utils::getValueAsFloat($target);
                    if ($float_val === null || $float_target === null) {
                        return new ConfigEvaluation(false);
                    }
                    return new ConfigEvaluation($float_val <= $float_target);
                }
            case 'version_gt': {
                    return new ConfigEvaluation(
                        Utils::versionCompareHelper($value, $target, function ($v1, $v2) {
                            return Utils::versionCompare($v1, $v2) > 0;
                        })
                    );
                }
            case 'version_gte': {
                    return new ConfigEvaluation(
                        Utils::versionCompareHelper($value, $target, function ($v1, $v2) {
                            return Utils::versionCompare($v1, $v2) >= 0;
                        })
                    );
                }
            case 'version_lt': {
                    return new ConfigEvaluation(
                        Utils::versionCompareHelper($value, $target, function ($v1, $v2) {
                            return Utils::versionCompare($v1, $v2) < 0;
                        })
                    );
                }
            case 'version_lte': {
                    return new ConfigEvaluation(
                        Utils::versionCompareHelper($value, $target, function ($v1, $v2) {
                            return Utils::versionCompare($v1, $v2) <= 0;
                        })
                    );
                }
            case 'version_eq': {
                    return new ConfigEvaluation(
                        Utils::versionCompareHelper($value, $target, function ($v1, $v2) {
                            return Utils::versionCompare($v1, $v2) === 0;
                        })
                    );
                }
            case 'version_neq': {
                    return new ConfigEvaluation(
                        Utils::versionCompareHelper($value, $target, function ($v1, $v2) {
                            return Utils::versionCompare($v1, $v2) !== 0;
                        })
                    );
                }
            case 'any': {
                    return new ConfigEvaluation(
                        Utils::matchStringInArray($value, $target, function ($a, $b) {
                            return strcasecmp($a, $b) === 0;
                        })
                    );
                }
            case 'none': {
                    return new ConfigEvaluation(
                        !Utils::matchStringInArray($value, $target, function ($a, $b) {
                            return strcasecmp($a, $b) === 0;
                        })
                    );
                }
            case 'any_case_sensitive': {
                    return new ConfigEvaluation(
                        Utils::matchStringInArray($value, $target, function ($a, $b) {
                            return strcmp($a, $b) === 0;
                        })
                    );
                }
            case 'none_case_sensitive': {
                    return new ConfigEvaluation(
                        !Utils::matchStringInArray($value, $target, function ($a, $b) {
                            return strcmp($a, $b) === 0;
                        })
                    );
                }
            case 'str_starts_with_any': {
                    return new ConfigEvaluation(
                        Utils::matchStringInArray($value, $target, function ($a, $b) {
                            return Utils::stringStartsWith($a, $b);
                        })
                    );
                }
            case 'str_ends_with_any': {
                    return new ConfigEvaluation(
                        Utils::matchStringInArray($value, $target, function ($a, $b) {
                            return Utils::stringEndsWith($a, $b);
                        })
                    );
                }
            case 'str_contains_any': {
                    return new ConfigEvaluation(
                        Utils::matchStringInArray($value, $target, function ($a, $b) {
                            return stripos($a, $b) !== false;
                        })
                    );
                }
            case 'str_contains_none': {
                    return new ConfigEvaluation(
                        !Utils::matchStringInArray($value, $target, function ($a, $b) {
                            return stripos($a, $b) !== false;
                        })
                    );
                }
            case 'str_matches': {
                    $str_val = Utils::getValueAsString($value);
                    if ($str_val === null) {
                        return new ConfigEvaluation(false);
                    }

                    if (!Utils::stringStartsWith('/', $target)) {
                        $target = '/' . $target;
                    }

                    if (!Utils::stringEndsWith('/', $target)) {
                        $target = $target . '/';
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
                return new ConfigEvaluation($value < $target);
            case 'after':
                return new ConfigEvaluation($value > $target);
            case 'on':
                $a = date('Y-m-d', $value);
                $b = date('Y-m-d', $target);
                return new ConfigEvaluation($a == $b);
            case 'in_segment_list':
            case 'not_in_segment_list':
                // TODO id lists
                return new ConfigEvaluation(false, "", [], [], true);
            default:
                return new ConfigEvaluation(false, "", [], [], true);
        }
    }


    function getFromIP($user, $field)
    {
        $ip = Utils::getFromUser($user, "ip");
        if ($ip === null || $field != "country") {
            return null;
        }
        return $this->ip3c->lookup($ip);
    }

    function getFromUserAgent($user, $field): ?string
    {
        $ua = Utils::getFromUser($user, "userAgent");
        if ($ua == null) {
            return null;
        }
        if (!is_string($ua) || strlen($ua) > 1000) {
            return null;
        }

        $res = $this->ua_parser->parse($ua);
        switch (strtolower($field)) {
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
}
