<?php

namespace Statsig;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/** Sanitizes diagnostic output without changing the original request or exception. */
class SecretRedactor
{
    private string $key;

    public function __construct(string $key)
    {
        $this->key = $key;
    }

    public function text(string $text): string
    {
        if ($this->key !== '') {
            foreach (array_unique([$this->key, rawurlencode($this->key), urlencode($this->key)]) as $variant) {
                $text = str_ireplace($variant, '[REDACTED]', $text);
            }
        }
        return preg_replace('~(/download_config_specs/)[^/?\s"\']+~i', '$1[REDACTED]', $text);
    }

    public function value($value, int $depth = 0)
    {
        if ($depth > 16) {
            return '[REDACTED]';
        }
        if (is_string($value)) {
            return $this->text($value);
        }
        if (is_array($value) || is_object($value)) {
            $result = [];
            foreach ((array) $value as $key => $item) {
                $safeKey = is_string($key) ? $this->text($key) : $key;
                $result[$safeKey] = $this->value($item, $depth + 1);
            }
            return $result;
        }
        return $value;
    }

    private function withoutTraceArguments(\Exception $error): \Exception
    {
        $trace = $error->getTrace();
        foreach ($trace as $index => $frame) {
            unset($frame['args'], $frame['object']);
            foreach (['file', 'class', 'function'] as $field) {
                if (isset($frame[$field])) {
                    $frame[$field] = $this->text($frame[$field]);
                }
            }
            $trace[$index] = $frame;
        }
        $property = new \ReflectionProperty(\Exception::class, 'trace');
        $property->setAccessible(true);
        $property->setValue($error, $trace);
        return $error;
    }

    public function exception(\Exception $error): \Exception
    {
        $message = $this->text($error->getMessage());
        try {
            if ($error instanceof ConnectException || $error instanceof RequestException) {
                $request = $error->getRequest();
                // Recreate only safe request metadata: the original may retain credentials in headers or body.
                $safeRequest = new Request($request->getMethod(), $this->text((string) $request->getUri()));
                if ($error instanceof ConnectException) {
                    return $this->withoutTraceArguments(new ConnectException($message, $safeRequest));
                }
                $response = $error->getResponse();
                $safeResponse = $response === null ? null : new Response($response->getStatusCode());
                if ($error instanceof \GuzzleHttp\Exception\ClientException && $safeResponse !== null) {
                    return $this->withoutTraceArguments(new \GuzzleHttp\Exception\ClientException($message, $safeRequest, $safeResponse));
                }
                if ($error instanceof \GuzzleHttp\Exception\ServerException && $safeResponse !== null) {
                    return $this->withoutTraceArguments(new \GuzzleHttp\Exception\ServerException($message, $safeRequest, $safeResponse));
                }
                return $this->withoutTraceArguments(new RequestException($message, $safeRequest, $safeResponse));
            }
        } catch (\Throwable $ignored) {
            // Fail closed if a malformed URL cannot be represented as a safe request.
        }
        return $this->withoutTraceArguments(new \RuntimeException($message, (int) $error->getCode()));
    }
}
