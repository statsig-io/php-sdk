<?php

namespace Statsig;

class StatsigOptions
{
    public ?string $tier = null;

    private string $config_file_path;
    private ?string $logging_file_path;

    function __construct(string $config_file_path, ?string $logging_file_path = null)
    {
        if ($config_file_path[0] !== '/') {
            $config_file_path = __DIR__ . '/' . $config_file_path;
        }
        $this->config_file_path = $config_file_path;

        if (empty($logging_file_path)) {
            $this->logging_file_path = null;
            return;
        }

        if ($logging_file_path[0] !== '/') {
            $logging_file_path = __DIR__ . '/' . $logging_file_path;
        }
        $this->logging_file_path = $logging_file_path;
    }

    function getConfigFilePath(): string
    {
        return $this->config_file_path;
    }

    function getLoggingFilePath(): ?string
    {
        return $this->logging_file_path;
    }

    function setEnvironmentTier(?string $tier)
    {
        $this->tier = $tier;
    }
}
