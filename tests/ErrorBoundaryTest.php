<?php

namespace Statsig\Test;

use PHPUnit\Framework\TestCase;
use Statsig\Adapters\IDataAdapter;
use Statsig\Exceptions\EventQueueSizeException;
use Statsig\Exceptions\InvalidSDKKeyException;
use Statsig\Exceptions\StatsigUserException;
use Statsig\StatsigServer;
use Statsig\StatsigOptions;
use Statsig\StatsigUser;

class ErrorBoundaryTest extends TestCase
{
    private IDataAdapter $data_adapter;

    protected function setup(): void
    {
        $contents = json_decode(file_get_contents(__DIR__ . "/statsig.cache"), true);
        $contents["fetch_time"] = floor(microtime(true) * 1000);
        $this->data_adapter = \Mockery::mock('Statsig\Adapters\LocalFileDataAdapter');
        $this->data_adapter->shouldReceive("get")->andReturn(json_encode($contents));
    }

    public function testInvalidSDKKey()
    {
        $this->expectException(InvalidSDKKeyException::class);
        $statsig = new StatsigServer('invalid-key', new StatsigOptions($this->data_adapter));
    }

    public function testInvalidStatsigUser(): void
    {
        $this->expectException(StatsigUserException::class);
        $statsig = new StatsigServer('secret-key', new StatsigOptions($this->data_adapter));
        $user = StatsigUser::withUserID('test-user');
        $user->setUserID('');
        $statsig->checkGate($user, 'nonexistent_gate');
    }

    public function testEventQueueSize(): void
    {
        $this->expectException(EventQueueSizeException::class);
        $options = new StatsigOptions($this->data_adapter);
        $options->setEventQueueSize(0);
    }
}
