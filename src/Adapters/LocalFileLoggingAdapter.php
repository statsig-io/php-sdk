<?php

namespace Statsig\Adapters;

use Exception;

class LocalFileLoggingAdapter implements ILoggingAdapter
{
    private ?string $file_path;
    private $file;

    function __construct(string $file_path)
    {
        if (empty($file_path)) {
            $this->file_path = null;
            return;
        }

        if ($file_path[0] !== '/') {
            $file_path = __DIR__ . '/' . $file_path;
        }
        $this->file_path = $file_path;
    }

    public function open()
    {
        if ($this->file_path === null) {
            return;
        }

        try {
            $open = @fopen($this->file_path, 'ab');
            if ($open !== false) {
                $this->file = $open;
                chmod($this->file_path, 0644);
            }
        } catch (Exception $e) {
            $this->file = null;
        }
    }

    public function close()
    {
        if ($this->file === null) {
            return;
        }

        fclose($this->file);
    }

    public function logEvents(array $events)
    {
        if ($this->file === null) {
            return;
        }

        try {
            $content = json_encode($events);
            $content .= "\n";

            @fwrite($this->file, $content);
        } catch (Exception $e) {
        }
    }
}