<?php

namespace Statsig;

class ConfigEvaluation
{
    public bool $bool_value;
    public $json_value;
    public string $rule_id;
    public bool $fetch_from_server;
    public array $secondary_exposures;

    function __construct(bool $bool_value, string $rule_id = "",  $json_value = [], array $secondary_exposures = [], bool $fetch_from_server = false)
    {
        $this->bool_value = $bool_value;
        $this->rule_id = $rule_id;
        $this->json_value = $json_value;
        $this->secondary_exposures = $secondary_exposures;
        $this->fetch_from_server = $fetch_from_server;
    }
}
