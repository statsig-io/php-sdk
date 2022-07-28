<?php

namespace Statsig\Test;

use PHPUnit\Framework\TestCase;
use Statsig\Adapters\LocalFileConfigAdapter;
use Statsig\Adapters\LocalFileLoggingAdapter;
use Statsig\StatsigServer;
use Statsig\StatsigOptions;
use Statsig\StatsigUser;
use Statsig\StatsigEvent;
use Throwable;

class StatsigServerTest extends TestCase
{
    private StatsigServer $statsig;

    protected function setup(): void
    {
        $config_adapter = new LocalFileConfigAdapter("../../tests/testdata.config");
        $logging_adapter = new LocalFileLoggingAdapter("../../tests/testdata.log");

        $this->statsig = new StatsigServer("secret-test", new StatsigOptions($config_adapter, $logging_adapter));
        $this->statsigUser = StatsigUser::withUserID("123");
        $this->statsigUser->setEmail("testuser@statsig.com");
        $this->randomUser = StatsigUser::withUserID("random");
        $this->randomUser->setPrivateAttributes(array(
            "test" => "I am private"
        ));
    }

    protected function tearDown(): void
    {
        unlink(__DIR__ . "/testdata.log");
    }

    public function testAll()
    {
        $on = $this->statsig->checkGate($this->statsigUser, "always_on_gate");
        $this->assertEquals(true, $on);

        $email = $this->statsig->checkGate($this->statsigUser, "on_for_statsig_email");
        $this->assertEquals(true, $email);

        $email = $this->statsig->checkGate($this->randomUser, "on_for_statsig_email");
        $this->assertEquals(false, $email);

        $this->statsig->flush();

        $dc = $this->statsig->getConfig($this->statsigUser, "test_config");
        $this->assertEquals(7, $dc->get("number", 0));
        $this->assertEquals("statsig", $dc->get("string", ""));
        $this->assertEquals(false, $dc->get("boolean", true));
        $this->assertEquals(92, $dc->get("i_dont_exist_num", 92));

        $dc = $this->statsig->getConfig($this->randomUser, "test_config");
        $this->assertEquals(4, $dc->get("number", 0));
        $this->assertEquals("default", $dc->get("string", ""));
        $this->assertEquals(true, $dc->get("boolean", false));
        $this->assertEquals("fallback", $dc->get("i_dont_exist", "fallback"));

        $this->statsig->flush();

        $exp = $this->statsig->getExperiment($this->statsigUser, "sample_experiment");
        $this->assertEquals(false, $exp->get("sample_parameter", false));
        $exp = $this->statsig->getExperiment($this->randomUser, "sample_experiment");
        $this->assertEquals(true, $exp->get("sample_parameter", false));

        $this->statsig->flush();

        $nonexistent = $this->statsig->checkGate($this->statsigUser, "nonexistent_gate");
        $this->assertEquals(false, $nonexistent);

        $nonexistent = $this->statsig->getConfig($this->statsigUser, "nonexistent_config");
        $this->assertEquals(false, $nonexistent->get("test", false));

        $event_default_time = new StatsigEvent("test1");
        $this->statsig->logEvent($event_default_time);

        $event_override_time = new StatsigEvent("test2");
        $time = round(microtime(true) * 1000);
        $event_override_time->setTime($time);
        $event_override_time->setValue("test_value");
        $event_override_time->setMetadata(array("hello" => "world"));
        $this->statsig->logEvent($event_override_time);

        $this->statsig->flush();

        $this->assertEquals(true, file_exists(__DIR__ . "/testdata.log"));
        $contents = file_get_contents(__DIR__ . "/testdata.log");
        $lines = explode("\n", $contents);
        $this->assertCount(5, $lines); // 4 flushes and a new line :)
        $events = [];
        foreach ($lines as $line) {
            if (!trim($line)) {
                continue;
            }

            $json = json_decode($line, true);
            $events = array_merge($events, $json);
        }
        $this->assertCount(11, $events);

        $this->verifyExposure(
            $events[0],
            "statsig::gate_exposure",
            "gate",
            "always_on_gate",
            "2DWuOvXQZWKvoaNm27dqcs",
        );
        $this->assertEquals("true", $events[0]["metadata"]["gateValue"]);

        $this->verifyExposure(
            $events[1],
            "statsig::gate_exposure",
            "gate",
            "on_for_statsig_email",
            "3jdTW54SQWbbxFFZJe7wYZ",
        );
        $this->assertEquals("true", $events[1]["metadata"]["gateValue"]);

        $this->verifyExposure(
            $events[2],
            "statsig::gate_exposure",
            "gate",
            "on_for_statsig_email",
            "default",
        );
        $this->assertEquals("random", $events[2]["user"]["userID"]);
        $this->assertEquals(array(
            "userID" => "random",
        ), $events[2]["user"]);
        $this->assertEquals("false", $events[2]["metadata"]["gateValue"]);

        $this->verifyExposure(
            $events[3],
            "statsig::config_exposure",
            "config",
            "test_config",
            "4lInPNRUnjUzaWNkEWLFA9",
        );

        $this->verifyExposure(
            $events[3],
            "statsig::config_exposure",
            "config",
            "test_config",
            "4lInPNRUnjUzaWNkEWLFA9",
        );

        $this->verifyExposure(
            $events[4],
            "statsig::config_exposure",
            "config",
            "test_config",
            "default",
        );

        $this->verifyExposure(
            $events[5],
            "statsig::config_exposure",
            "config",
            "sample_experiment",
            "5yQbPMfmKQdiRV35hS3B2l",
        );

        $this->verifyExposure(
            $events[6],
            "statsig::config_exposure",
            "config",
            "sample_experiment",
            "5yQbPNUpd8mNbkB0SZZeln",
        );

        $this->verifyExposure(
            $events[7],
            "statsig::gate_exposure",
            "gate",
            "nonexistent_gate",
            "",
        );
        $this->assertEquals("false", $events[7]["metadata"]["gateValue"]);

        $this->verifyExposure(
            $events[8],
            "statsig::config_exposure",
            "config",
            "nonexistent_config",
            "",
        );

        $this->assertEquals("test1", $events[9]["eventName"]);
        $this->assertLessThanOrEqual(round(microtime(true) * 1000), $events[9]["time"]);

        $this->assertEquals("test2", $events[10]["eventName"]);
        $this->assertEquals($time, $events[10]["time"]);
        $this->assertEquals("test_value", $events[10]["value"]);
        $this->assertEquals("world", $events[10]["metadata"]["hello"]);

        $bad_user = StatsigUser::withUserID("a_user");
        $bad_user->setUserID(null);

        self::assertActionThrows(fn () => $this->statsig->checkGate($bad_user, "always_on_gate"));
        self::assertActionThrows(fn () => $this->statsig->getConfig($bad_user, "test_config"));
        self::assertActionThrows(fn () => $this->statsig->getExperiment($bad_user, "sample_experiment"));
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

    private static function assertActionThrows($action)
    {
        $was_caught = false;
        try {
            $action();
        } catch (Throwable $exception) {
            $was_caught = true;
        }

        self::assertTrue($was_caught);
    }
}
