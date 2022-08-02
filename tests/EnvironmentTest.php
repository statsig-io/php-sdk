<?php

namespace Statsig\Test;

use PHPUnit\Framework\TestCase;
use Statsig\Adapters\LocalFileConfigAdapter;
use Statsig\Adapters\LocalFileLoggingAdapter;
use Statsig\StatsigServer;
use Statsig\StatsigOptions;
use Statsig\StatsigUser;
use Statsig\StatsigEvent;

class EnvironmentTest extends TestCase
{
    private StatsigServer $statsig;
    private StatsigUser  $statsig_user;

    protected function setUp(): void
    {
        $actual_adapter = new LocalFileConfigAdapter("../../tests/testdata.config");
        $specs = $actual_adapter->getConfigSpecs();
        $specs->fetchTime = floor(microtime(true) * 1000);
        $mock_config_adapter = \Mockery::mock('Statsig\Adapters\LocalFileConfigAdapter');
        $mock_config_adapter->shouldReceive("getConfigSpecs")->andReturn($specs);

        $logging_adapter = new LocalFileLoggingAdapter("../../tests/testdata.log");
        $options = new StatsigOptions($mock_config_adapter, $logging_adapter);
        $options->setEnvironmentTier("development");
        $this->statsig = new StatsigServer("secret-test", $options);
        $this->statsig_user = StatsigUser::withUserID("123");
        $this->statsig_user->setEmail("testuser@statsig.com");
    }

    protected function tearDown(): void
    {
        unlink(__DIR__ . "/testdata.log");
    }

    public function testAll()
    {
        $on = $this->statsig->checkGate($this->statsig_user, "always_on_gate");
        $this->assertEquals(true, $on);

        $this->statsig->getConfig($this->statsig_user, "test_config");

        $event_default_time = new StatsigEvent("test1");
        $this->statsig->logEvent($event_default_time);

        $this->statsig->flush();

        $this->assertEquals(true, file_exists(__DIR__ . "/testdata.log"));
        $contents = file_get_contents(__DIR__ . "/testdata.log");
        $lines = explode("\n", $contents);
        $this->assertCount(2, $lines); // 1 flush and a new line :)
        $events = [];
        foreach ($lines as $line) {
            if (!trim($line)) {
                continue;
            }

            $json = json_decode($line, true);
            $events = array_merge($events, $json);
        }
        $this->assertCount(3, $events);

        $this->verifyExposure(
            $events[0],
            "statsig::gate_exposure",
            "gate",
            "always_on_gate",
            "2DWuOvXQZWKvoaNm27dqcs",
        );
        $this->assertEquals("true", $events[0]["metadata"]["gateValue"]);
        $this->assertEquals("development", $events[0]["user"]["statsigEnvironment"]["tier"]);

        $this->verifyExposure(
            $events[1],
            "statsig::config_exposure",
            "config",
            "test_config",
            "4lInPNRUnjUzaWNkEWLFA9",
        );
        $this->assertEquals("development", $events[1]["user"]["statsigEnvironment"]["tier"]);

        $this->assertEquals("test1", $events[2]["eventName"]);
        $this->assertLessThanOrEqual(round(microtime(true) * 1000), $events[2]["time"]);
        $this->assertEquals("development", $events[2]["user"]["statsigEnvironment"]["tier"]);
    }

    public function verifyExposure(
        $event,
        $name,
        $key,
        $value,
        $ruleID
    ) {
        $this->assertEquals($name, $event["eventName"]);
        $this->assertEquals($value, $event["metadata"][$key]);
        $this->assertEquals($ruleID, $event["metadata"]["ruleID"]);
        $this->assertLessThanOrEqual(round(microtime(true) * 1000), $event["time"]);
    }
}
