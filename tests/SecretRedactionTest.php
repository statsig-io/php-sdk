<?php

namespace Statsig\Test;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Statsig\ErrorBoundary;
use Statsig\SecretRedactor;
use Statsig\StatsigNetwork;

class SecretRedactionTest extends TestCase
{
    public function testEncodedAndRepeatedKeysAreRedacted(): void
    {
        $key = 'secret-a+b/c ?';
        $redactor = new SecretRedactor($key);
        $value = $redactor->text('status 503 ' . $key . ' ' . $key . ' ' . rawurlencode($key));
        $this->assertStringNotContainsString($key, $value);
        $this->assertStringNotContainsString(rawurlencode($key), $value);
        $this->assertStringContainsString('status 503', $value);
        $this->assertStringNotContainsString('unknown', $redactor->text('/download_config_specs/unknown.json'));
    }

    public function testCdnConnectionFailureDoesNotExposeRequestOrCause(): void
    {
        $key = 'secret-test+punctuation';
        $network = new StatsigNetwork();
        $network->setSdkKey($key);
        $requestSeen = null;
        $mock = new MockHandler([function ($request) use (&$requestSeen, $key) {
            $requestSeen = $request;
            throw new ConnectException('timeout ' . $request->getUri() . ' ' . $key, $request, new \RuntimeException('cause ' . $key));
        }]);
        $client = new Client(['base_uri' => 'https://example.test/v1/', 'handler' => HandlerStack::create($mock)]);
        TestUtils::setPrivatePropOnInstance('cdn_client', $client, $network);
        try {
            $network->getFromCDNRequest('download_config_specs');
            $this->fail('Expected connection failure');
        } catch (ConnectException $error) {
            $this->assertStringContainsString($key, (string) $requestSeen->getUri());
            $this->assertStringNotContainsString($key, (string) $error);
            $this->assertStringNotContainsString($key, serialize($error));
            $this->assertStringNotContainsString($key, (string) $error->getRequest()->getUri());
            $this->assertNull($error->getPrevious());
            $this->assertStringContainsString('timeout', $error->getMessage());
        }
    }

    public function testHttpErrorPreservesStatusAndRemovesRawRequest(): void
    {
        $key = 'secret-status-123';
        $request = new \GuzzleHttp\Psr7\Request('GET', 'https://example.test/download_config_specs/' . $key . '.json', ['Authorization' => $key]);
        $original = new \GuzzleHttp\Exception\ClientException('HTTP 403 ' . $key, $request, new Response(403, ['X-Secret' => $key], $key));
        $safe = (new SecretRedactor($key))->exception($original);
        $this->assertInstanceOf(\GuzzleHttp\Exception\ClientException::class, $safe);
        $this->assertSame(403, $safe->getResponse()->getStatusCode());
        $this->assertNull($safe->getPrevious());
        $this->assertStringNotContainsString($key, (string) $safe);
        $this->assertStringNotContainsString($key, \GuzzleHttp\Psr7\Message::toString($safe->getRequest()));
        $this->assertStringNotContainsString($key, (string) $safe->getResponse()->getBody());
    }

    public function testCdnSuccessKeepsOriginalKeyInRequest(): void
    {
        $key = 'secret-success-123';
        $network = new StatsigNetwork();
        $network->setSdkKey($key);
        $requestSeen = null;
        $mock = new MockHandler([function ($request) use (&$requestSeen) {
            $requestSeen = $request;
            return new Response(200, [], '{"value":true}');
        }]);
        TestUtils::setPrivatePropOnInstance('cdn_client', new Client(['base_uri' => 'https://example.test/v1/', 'handler' => HandlerStack::create($mock)]), $network);
        $this->assertSame(['value' => true], $network->getFromCDNRequest('download_config_specs'));
        $this->assertStringContainsString($key, (string) $requestSeen->getUri());
    }

    public function testExceptionReportSanitizesNestedExtrasAndPreservesHeader(): void
    {
        $key = 'secret-quote"=+';
        $boundary = new ErrorBoundary($key);
        $sent = null;
        $mock = \Mockery::mock(Client::class)->makePartial();
        $mock->shouldReceive('request')->once()->andReturnUsing(function ($method, $url, $options) use (&$sent) {
            $sent = $options;
            return new Response(202);
        });
        TestUtils::setPrivatePropOnInstance('client', $mock, $boundary);
        $boundary->logException(new \RuntimeException('outer ' . $key, 0, new \RuntimeException('inner ' . $key)), 'tag', [
            'nested' => ['details' => $key, 'encoded' => rawurlencode($key)],
        ]);
        $body = json_decode($sent['body'], true);
        $this->assertIsArray($body);
        $this->assertStringNotContainsString($key, json_encode($body));
        $this->assertStringNotContainsString(rawurlencode($key), json_encode($body));
        $this->assertStringContainsString('[REDACTED]', $body['exception']);
        $this->assertSame($key, $sent['headers']['STATSIG-API-KEY']);
    }
}
