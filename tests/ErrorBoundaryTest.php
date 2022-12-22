<?php

namespace Statsig\Test;

use PHPUnit\Framework\TestCase;
use Statsig\Adapters\IDataAdapter;
use Statsig\Exceptions\EventQueueSizeException;
use Statsig\Exceptions\StatsigUserException;
use Statsig\StatsigServer;
use Statsig\StatsigOptions;
use Statsig\StatsigUser;
use Exception;

class ErrorBoundaryTest extends TestCase
{
    private IDataAdapter  $data_adapter;
    private StatsigOptions $options;

    protected function setup(): void
    {
        $contents = json_decode(file_get_contents(__DIR__ . "/statsig.cache"), true);
        $contents["fetch_time"] = floor(microtime(true) * 1000);
        $this->data_adapter = \Mockery::mock('Statsig\Adapters\LocalFileDataAdapter');
        $this->data_adapter->shouldReceive("get")->andReturn(json_encode($contents));
        $this->options = new StatsigOptions($this->data_adapter);
    }

    public function testInvalidStatsigUser(): void
    {
        $this->expectException(StatsigUserException::class);
        $statsig = new StatsigServer('secret-key', $this->options);
        $user = StatsigUser::withUserID('test-user');
        $user->setUserID('');
        $statsig->checkGate($user, 'nonexistent_gate');
    }

    public function testEventQueueSize(): void
    {
        $this->expectException(EventQueueSizeException::class);
        $this->options->setEventQueueSize(0);
    }

    public function testLogException(): void
    {
        $statsig = new StatsigServer('secret-key',  $this->options);
        $error_boundary = TestUtils::getPrivatePropOnInstance('error_boundary', $statsig);
        $real_http_client = TestUtils::getPrivatePropOnInstance('client', $error_boundary);
        $hit = false;
        $mock_http_client = \Mockery::mock('\GuzzleHttp\Client', [])->makePartial();
        $mock_http_client->shouldReceive('request')
            ->andReturnUsing(function ($method, $uri, $options) use ($real_http_client, &$hit) {
                $hit = true;
                return $real_http_client->request($method, 'https://test-error-boundary-endpoint', $options);
            });
        TestUtils::setPrivatePropOnInstance('client', $mock_http_client, $error_boundary);
        $mock_evaluator = \Mockery::mock('Statsig\Evaluator');
        $mock_evaluator->shouldReceive('checkGate')->andThrow(new Exception('Failed to call checkGate in Evaluator'));
        TestUtils::setPrivatePropOnInstance('evaluator', $mock_evaluator, $statsig);

        $user = StatsigUser::withUserID('test-user');
        $statsig->checkGate($user, 'nonexistent_gate');
        self::assertTrue($hit);

        $hit = false;
        $statsig->checkGate($user, 'nonexistent_gate');
        self::assertFalse($hit); // Same exception should not be logged twice
    }
}
