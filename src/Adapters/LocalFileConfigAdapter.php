<?php

namespace Statsig\Adapters;

use Exception;

class LocalFileConfigAdapter implements IConfigAdapter
{
    private string $file_path;

    function __construct(string $file_path)
    {
        if ($file_path[0] !== '/') {
            $file_path = __DIR__ . '/' . $file_path;
        }

        $this->file_path = $file_path;
    }

    function getConfigSpecs(): array
    {
        try {
            $contents = @file_get_contents($this->file_path);
            if ($contents !== false) {
                return json_decode($contents, true);
            }
        } catch (Exception $_e) {
        }

        return [
            'gates' => [],
            'configs' => []
        ];
    }
}