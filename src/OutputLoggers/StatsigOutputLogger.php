<?php

namespace Statsig\OutputLoggers;

use Statsig\OutputLoggers\IOutputLogger;

 /**
  * Default logger class 
  * Send error and warning message to defined error handling routes
  */
class StatsigOutputLogger implements IOutputLogger 
{
  public function warning(string $message) {
    error_log("Warning: ".$message);
  }
  public function error(string $message) {
    error_log("Error: ".$message);
  }
}