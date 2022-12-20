<?php

namespace Statsig\Exceptions;

use Exception;
use Throwable;

class StatsigBaseException extends Exception
{
    public function __construct($message, $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function __toString()
    {
        return "Statsig:" . __CLASS__;
    }
}
