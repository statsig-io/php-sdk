<?php

namespace Statsig\Adapters;

class LocalFileDataAdapter implements IDataAdapter
{
    private string $working_directory;

    function __construct(string $working_directory = "/tmp/statsig/")
    {
        $this->working_directory = self::resolvePath($working_directory);
    }

    public function get(string $key): ?string
    {
        $path = $this->working_directory . base64_encode($key);

        $contents = @file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        return $contents;
    }

    public function set(string $key, ?string $value)
    {
        $path = $this->working_directory . base64_encode($key);
        if ($value === null) {
            unlink($path);
            return;
        }

        self::filePutContents($path, $value);
    }

    private static function resolvePath(string $path): string
    {
        if (substr($path, -1) !== "/") {
            $path = $path . "/";
        }

        if ($path[0] !== '/') {
            return __DIR__ . '/' . $path;
        }
        return $path;
    }

    private static function filePutContents(string $path, string $contents)
    {
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $contents);
    }
}