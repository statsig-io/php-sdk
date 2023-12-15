<?php

namespace Statsig\Test;

use PHPUnit\Util\Exception;
use Statsig\StatsigNetwork;
use Statsig\StatsigServer;

abstract class TestUtils
{
    public static function mockNetworkOnStatsigInstance(StatsigServer $server, callable $on_request, $crash_on_real_call = false)
    {
        $real_network = self::getPrivatePropOnInstance("network", $server);
        $mock_network = self::getMockNetwork($on_request, $crash_on_real_call ? null : $real_network);

        $logger = self::getPrivatePropOnInstance("logger", $server);
        $store = self::getPrivatePropOnInstance("store", $server);

        self::setPrivatePropOnInstance('network', $mock_network, $server);
        self::setPrivatePropOnInstance('network', $mock_network, $logger);
        self::setPrivatePropOnInstance('network', $mock_network, $store);
    }

    /**
     * @param array $mocks Associative array of key to mock result.
     *  For postRequest, the format is endpoint => dict, ie "download_config_specs" => ["feature_gates" => []]
     *  For multiGetRequest, the format is first key => multiGetRequest, ie "an_id_ist" => ["an_id_list" => "+1\n+2", "a_second_id_list" => +a\n-b]
     * @param StatsigNetwork|null $real_network provide if you want un-mocked endpoints to be hit
     * @return object The mocked StatsigNetwork object
     */
    public static function getMockNetwork(callable $on_request, StatsigNetwork $real_network = null): object
    {
        // @php-ignore
        $mock_network = \Mockery::mock('Statsig\StatsigNetwork', [])->makePartial();
        $mock_network->shouldReceive('getSDKKey')->andReturnUsing(function () use ($real_network) {
            if ($real_network == null) {
                throw new Exception("Attempted to get SDK key");
            }

            return $real_network->getSDKKey();
        }

        $mock_network->shouldReceive('postRequest')
            ->andReturnUsing(function ($endpoint, $input) use ($on_request, $real_network, $mock_network) {
                $mock = $on_request("POST", $endpoint, $input);
                if ($mock) {
                    return $mock;
                }

                if ($real_network == null) {
                    throw new Exception("Attempted to make POST network call to '" . $endpoint . "'");
                }

                return $real_network->postRequest($endpoint, $input);
            });

        // Just checks the first key then returns the mock
        $mock_network->shouldReceive("multiGetRequest")
            ->andReturnUsing(function ($input) use ($on_request, $real_network, $mock_network) {
                $all_mocked = true;

                $mocks = array_map(function(array $value) use ($on_request, &$all_mocked) {
                    $mock = $on_request("GET", $value["url"], $value);
                    if (!$mock) {
                        $all_mocked = false;
                    }
                    return $mock;
                }, $input);

                if ($all_mocked) {
                    return $mocks;
                }

                if ($real_network == null) {
                    throw new Exception("Attempted to make GET network call to '" . json_encode($input) . "'");
                }

                return $real_network->multiGetRequest($input);
            });

        return $mock_network;
    }

    public static function setPrivatePropOnInstance($prop_name, $props_value, $instance)
    {
        $reflection = new \ReflectionClass(get_class($instance));
        $property = $reflection->getProperty($prop_name);
        $property->setAccessible(true);
        $property->setValue($instance, $props_value);
    }

    public static function getPrivatePropOnInstance($prop_name, $instance)
    {
        $reflection = new \ReflectionClass(get_class($instance));
        $property = $reflection->getProperty($prop_name);
        $property->setAccessible(true);
        return $property->getValue($instance);
    }
}
