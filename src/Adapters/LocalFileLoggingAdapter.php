<?php

namespace Statsig\Adapters;

use Exception;

class LocalFileLoggingAdapter implements ILoggingAdapter
{
    private ?string $file_path;
    private /* ?resource */ $file;

    function __construct(string $file_path = "/tmp/statsig.logs")
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

    public function enqueueEvents(array $events)
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

    public function getQueuedEvents(): array
    {
        // Rename the file
        $dir = dirname($this->file_path);
        $working_file = $dir . '/statsig-' . mt_rand() . '.log';

        if (!file_exists($this->file_path)) {
            print("file: $this->file_path does not exist");
            exit(0);
        }

        if (!rename($this->file_path, $working_file)) {
            print("error renaming from $this->file_path to $working_file\n");
            exit(1);
        }

        $contents = file_get_contents($working_file);
        $lines = explode("\n", $contents);


        $events = [];
        foreach ($lines as $line) {
            if (!trim($line)) {
                continue;
            }
            $events = array_merge($events, json_decode($line, true));
        }

        unlink($working_file);
        return $events;
    }
}