<?php

namespace Statsig\Adapters;

interface IConfigAdapter
{
    /**
     * @return array<string, array>
     */
    function getConfigSpecs(): array;
}