<?php

namespace Statsig;

class Layer
{
    private string $name;
    private array $value;
    private string $rule_id;
    private \Closure $exposure_log_fn;
    private ?string $group_name;
    private ?string $allocated_experiment_name;

    function __construct(string $name, array $value = [], string $rule_id = "", ?callable $exposure_log_fn = null, ?string $group_name = null, ?string $allocated_experiment_name = null)
    {
        $this->name = $name;
        $this->rule_id = $rule_id;
        $exposure_log_fn = $exposure_log_fn ?? function () {};
        $this->exposure_log_fn = \Closure::fromCallable($exposure_log_fn);
        $this->group_name = $group_name;
        $this->allocated_experiment_name = $allocated_experiment_name;

        // We re-decode here to treat associative arrays as objects, this allows us
        // to differentiate between array ([1,2]) and object (['a' => 'b'])
        $this->value = (array) json_decode(json_encode($value), null, 512, JSON_BIGINT_AS_STRING);
    }

    /**
     * Returns the value if field exists, $default otherwise.
     */
    function get(string $field, $default = null)
    {
        if (!array_key_exists($field, $this->value)) {
            return $default;
        }
        $val = $this->value[$field];
        $this->logParameterExposure($field);
        return $val;
    }

    /**
     * Returns the value if field exists and is the same type as $default, $default otherwise.
     * NOTE: Large integers are decoded as strings to avoid integer overflow
     */
    function getTyped(string $field, $default)
    {
        if (!array_key_exists($field, $this->value)) {
            return $default;
        }
        $val = $this->value[$field];
        if ($default !== null && gettype($val) !== gettype($default)) {
            return $default;
        }
        $this->logParameterExposure($field);
        return $val;
    }

    function getName(): string
    {
        return $this->name;
    }

    function getRuleID(): string
    {
        return $this->rule_id;
    }

    function logParameterExposure($parameter): void
    {
        ($this->exposure_log_fn)($parameter);
    }

    function getGroupName(): ?string
    {
        return $this->group_name;
    }

    function getAllocatedExperimentName(): ?string
    {
        return $this->allocated_experiment_name;
    }
}
