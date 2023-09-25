<?php

namespace Statsig;

class ConfigEvaluation
{
    public bool $bool_value;
    public $json_value;
    public string $rule_id;
    public bool $unsupported;
    public array $secondary_exposures;
    public array $undelegated_secondary_exposures;
    public string $allocated_experiment;
    public array $explicit_parameters;
    public bool $is_experiment_group;

    function __construct(
        bool $bool_value, 
        string $rule_id = "",
        $json_value = [],
        array $secondary_exposures = [],
        bool $unsupported = false,
        string $allocated_experiment = "",
        array $explicit_parameters = [],
        bool $is_experiment_group = false)
    {
        $this->bool_value = $bool_value;
        $this->rule_id = $rule_id;
        $this->json_value = $json_value;
        $this->secondary_exposures = $secondary_exposures;
        $this->undelegated_secondary_exposures = $secondary_exposures;
        $this->unsupported = $unsupported;
        $this->allocated_experiment = $allocated_experiment;
        $this->explicit_parameters = $explicit_parameters;
        $this->is_experiment_group = $is_experiment_group;
    }
}
