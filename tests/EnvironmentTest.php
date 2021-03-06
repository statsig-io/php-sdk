<?php

namespace Statsig\Test;

use PHPUnit\Framework\TestCase;
use Statsig\StatsigServer;
use Statsig\StatsigOptions;
use Statsig\StatsigUser;
use Statsig\StatsigEvent;

class EnvironmentTest extends TestCase {

    private $statsig;

    public function setUp() {
        $options = new StatsigOptions(__DIR__."/../tests/testdata.config", __DIR__."/../tests/testdata.log");
        $options->setEnvironmentTier("development");
        $this->statsig = new StatsigServer("secret-test", $options);
        $this->statsigUser = new StatsigUser("123");
        $this->statsigUser->setEmail("testuser@statsig.com");
    }

    public function testAll() {
        $on = $this->statsig->checkGate($this->statsigUser, "always_on_gate");
        $this->assertEquals(true, $on);

        $dc = $this->statsig->getConfig($this->statsigUser, "test_config");


        $event_default_time = new StatsigEvent("test1");
        $this->statsig->logEvent($event_default_time);

        $this->statsig->flush();

        $this->assertEquals(true, file_exists(__DIR__."/testdata.log"));
        $contents = file_get_contents(__DIR__."/testdata.log");
        $lines = explode("\n", $contents);
        $this->assertEquals(2, count($lines)); // 1 flush and a new line :)
        $events = [];
        foreach ($lines as $line) {
            if (!trim($line)) {
                continue;
            }
            
            $json = json_decode($line, true);
            $events = array_merge($events, $json);
        }
        $this->assertEquals(3, count($events));

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

    public function tearDown() {
        unlink(__DIR__."/testdata.log");
    }
}