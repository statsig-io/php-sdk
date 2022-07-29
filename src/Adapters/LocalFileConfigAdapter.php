<?php

namespace Statsig\Adapters;

use Exception;
use Statsig\ConfigSpecs;

class LocalFileConfigAdapter implements IConfigAdapter
{
    private string $file_path;

    function __construct(string $file_path = "/tmp/statsig.configs")
    {
        if ($file_path[0] !== '/') {
            $file_path = __DIR__ . '/' . $file_path;
        }

        $this->file_path = $file_path;
    }

    function getConfigSpecs(): ConfigSpecs
    {
        $specs = new ConfigSpecs();
        try {
            $contents = @file_get_contents($this->file_path);
            if ($contents !== false) {
                $json = json_decode($contents, true);
                $specs->gates = $json['gates'];
                $specs->configs = $json["configs"];
            }
        } catch (Exception $_e) {
        }

        return $specs;
    }

    public function updateConfigSpecs(ConfigSpecs $specs)
    {
        $dir = dirname($this->file_path);
        $temp = $dir . '/' . mt_rand() . basename($this->file_path);

        file_put_contents($temp, json_encode($specs));

        if (!rename($temp, $this->file_path)) {
            print("error renaming from $temp to $this->file_path\n");
            exit(1);
        }
    }
}