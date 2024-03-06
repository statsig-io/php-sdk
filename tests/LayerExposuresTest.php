<?php

namespace Statsig\Test;

use PHPUnit\Framework\TestCase;
use Statsig\Adapters\LocalFileDataAdapter;
use Statsig\Adapters\LocalFileLoggingAdapter;
use Statsig\ConfigSpecs;
use Statsig\StatsigServer;
use Statsig\StatsigUser;
use Statsig\StatsigOptions;

class LayerExposuresTest extends TestCase
{
    private StatsigServer $statsig;
    private StatsigUser $user;
    private $methods = ["getTyped", "get"];

    protected function setup(): void
    {
        $config_adapter = new LocalFileDataAdapter("/tmp/statsig/layer_exposures_test");
        $logging_adapter = new LocalFileLoggingAdapter("../../tests/testdata.log");
        $options = new StatsigOptions($config_adapter, $logging_adapter);
        $this->statsig = new StatsigServer("secret-key", $options);
        $this->user = StatsigUser::withUserID("123");

        $contents = json_decode(file_get_contents(__DIR__ . "/layer_exposures_download_config_specs.json"), true, 512, JSON_BIGINT_AS_STRING);
        $network = TestUtils::mockNetworkOnStatsigInstance($this->statsig, function ($method, $endpoint, $input) use ($contents) {
            return $endpoint == "download_config_specs" ? $contents : null;
        });
        ConfigSpecs::sync($config_adapter, $network);
    }

    protected function tearDown(): void
    {
        if (file_exists(__DIR__ . "/testdata.log")) {
            unlink(__DIR__ . "/testdata.log");
        }
    }

    public function testDoesNotLogOnGetLayer()
    {
        $this->statsig->getLayer($this->user, "unallocated_layer");
        $this->statsig->flush();

        $this->assertEventsFlushed(0);
    }

    public function testDoesNotLogOnInvalidType()
    {
        $layer = $this->statsig->getLayer($this->user, "unallocated_layer");
        $layer->get("invalid_key", "err");
        $layer->getTyped("an_int", "err");
        $this->statsig->flush();

        $this->assertEventsFlushed(0);
    }

    public function testUnallocatedLayerLogging()
    {
        foreach ($this->methods as $index => $method) {
            $layer = $this->statsig->getLayer($this->user, "unallocated_layer");
            call_user_func_array(array($layer, $method), array("an_int", 0));
            $this->statsig->flush();
            
            $this->assertEventsFlushed(1 + $index);
        }
    }

    public function testExplicitVsImplicitParameterLogging()
    {
        foreach ($this->methods as $index => $method) {
            $layer = $this->statsig->getLayer($this->user, "explicit_vs_implicit_parameter_layer");
            call_user_func_array(array($layer, $method), array("an_int", 0));
            call_user_func_array(array($layer, $method), array("a_string", "err"));
            $this->statsig->flush();

            $events = $this->assertEventsFlushed(1 + $index, 2 * (1 + $index));
            $this->assertEquals($events[0]["metadata"]["config"], "explicit_vs_implicit_parameter_layer");
            $this->assertEquals($events[0]["metadata"]["ruleID"], "alwaysPass");
            $this->assertEquals($events[0]["metadata"]["allocatedExperiment"], "experiment");
            $this->assertEquals($events[0]["metadata"]["parameterName"], "an_int");
            $this->assertEquals($events[0]["metadata"]["isExplicitParameter"], "true");

            $this->assertEquals($events[1]["metadata"]["config"], "explicit_vs_implicit_parameter_layer");
            $this->assertEquals($events[1]["metadata"]["ruleID"], "alwaysPass");
            $this->assertEquals($events[1]["metadata"]["allocatedExperiment"], "");
            $this->assertEquals($events[1]["metadata"]["parameterName"], "a_string");
            $this->assertEquals($events[1]["metadata"]["isExplicitParameter"], "false");
        }
    }

    public function testDiffferentObjectTypeLogging()
    {
        $expected_events = 0;
        foreach ($this->methods as $index => $method) {
            $layer = $this->statsig->getLayer($this->user, "different_object_type_logging_layer");
            call_user_func_array(array($layer, $method), array("a_bool", false));
            call_user_func_array(array($layer, $method), array("an_int", 0));
            call_user_func_array(array($layer, $method), array("a_double", 0.0));
            $long_value = call_user_func_array(array($layer, $method), array("a_long", 0)); // expected to fail on getTyped
            call_user_func_array(array($layer, $method), array("a_string", "err"));
            call_user_func_array(array($layer, $method), array("an_array", []));
            call_user_func_array(array($layer, $method), array("an_object", (object)[]));
            $this->statsig->flush();

            if ($method === "getTyped") {
                $expected_events += 6;
            } else {
                $this->assertTrue("9223372036854776000" === $long_value);
                $expected_events += 7;
            }
            $this->assertEventsFlushed(1 + $index, $expected_events);
        }
    }

    public function testLogsUserAndEventName()
    {
        foreach ($this->methods as $index => $method) {
            $layer = $this->statsig->getLayer($this->user, "unallocated_layer");
            call_user_func_array(array($layer, $method), array("an_int", 0));
            $this->statsig->flush();

            $events = $this->assertEventsFlushed(1 + $index, 1 + $index);
            $this->assertEquals($events[0]["eventName"], "statsig::layer_exposure");
            $this->assertEquals($events[0]["user"]["userID"], "123");
        }
    }

    private function assertEventsFlushed(int $flush_count = 0, ?int $expected_events = null): array
    {
        if ($flush_count === 0) {
            $this->assertEquals(false, file_exists(__DIR__ . "/testdata.log"));
            return [];
        }
        $this->assertEquals(true, file_exists(__DIR__ . "/testdata.log"));
        $contents = file_get_contents(__DIR__ . "/testdata.log");
        $lines = explode("\n", $contents);
        $this->assertCount($flush_count + 1, $lines); // +1 for new line
        if (!$expected_events) {
            return $lines;
        }
        $events = [];
        foreach ($lines as $line) {
            if (!trim($line)) {
                continue;
            }

            $json = json_decode($line, true, 512, JSON_BIGINT_AS_STRING);
            $events = array_merge($events, $json);
        }
        $this->assertCount($expected_events, $events);
        return $events;
    }
}
