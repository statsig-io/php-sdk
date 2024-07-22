<?php

namespace Statsig;

class FeatureGate
{
    private string $name;
    private bool $value;
    private string $rule_id;
    private array $secondary_exposures;
    private ?string $group_name;
    private ?string $id_type;
    private ?EvaluationDetails $evaluation_details;

    function __construct(string $name, bool $value = false, string $rule_id = "", array $secondary_exposures = [], ?string $group_name = null, ?string $id_type = null, ?EvaluationDetails $evaluation_details = null)
    {
        $this->name = $name;
        $this->rule_id = $rule_id;
        $this->secondary_exposures = $secondary_exposures;
        $this->group_name = $group_name;
        $this->id_type = $id_type;
        $this->value = $value;
        $this->evaluation_details = $evaluation_details;
    }

    function getName(): string
    {
        return $this->name;
    }

    function getValue(): bool
    {
        return $this->value;
    }

    function getRuleID(): string
    {
        return $this->rule_id;
    }

    function getSecondaryExposures(): array
    {
        return $this->secondary_exposures;
    }

    function getGroupName(): ?string
    {
        return $this->group_name;
    }

    function getIDType(): ?string
    {
        return $this->id_type;
    }
    
    function getEvaluationDetails(): ?EvaluationDetails
    {
        return $this->evaluation_details;
    }
}
