<?php

namespace Statsig\OutputLoggers;

interface IOutputLogger
{
    function error(string $message);
    function warning(string $message);
}
