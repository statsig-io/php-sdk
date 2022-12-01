<?php

namespace Statsig\Adapters;

interface IDataAdapter
{
    function get(string $key): ?string;
    function set(string $key, ?string $value);
}

