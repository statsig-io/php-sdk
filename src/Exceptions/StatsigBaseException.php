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
        return get_class($this);
    }

    public function logToStderr()
    {
        $message = sprintf('[%s] %s' . PHP_EOL, $this->__toString(), $this->getMessage());
        error_log($message);
    }
}
