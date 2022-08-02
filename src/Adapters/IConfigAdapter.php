<?php

namespace Statsig\Adapters;

use Statsig\ConfigSpecs;

interface IConfigAdapter
{
    function getConfigSpecs(): ?ConfigSpecs;
    function updateConfigSpecs(ConfigSpecs $specs);
}