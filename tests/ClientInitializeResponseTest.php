<?php

namespace Statsig\Test;

use PHPUnit\Framework\TestCase;
use Statsig\Adapters\LocalFileDataAdapter;
use Statsig\Adapters\LocalFileLoggingAdapter;
use Statsig\StatsigServer;
use Statsig\StatsigUser;
use Statsig\StatsigOptions;

class ClientInitializeResponseTest extends TestCase
{
    private StatsigServer $statsig;
    private StatsigUser $user;
    private string $client_key;
    private string $secret_key;

    protected function setup(): void
    {
        $this->client_key = getenv("test_client_key");
        $this->secret_key = getenv("test_api_key");
        $config_adapter = new LocalFileDataAdapter("/tmp/statsig/client_initialize_response_test");
        $logging_adapter = new LocalFileLoggingAdapter("../../tests/testdata.log");
        $options = new StatsigOptions($config_adapter, $logging_adapter);
        $this->statsig = new StatsigServer($this->secret_key, $options);
        $this->user = StatsigUser::withUserID("123");
        $this->user->setEmail("test@statsig.com");
        $this->user->setCountry("US");
        $this->user->setCustom(array("test" => "123"));
        $this->user->setCustomIDs(array("stableID" => "12345"));
    }

    protected function tearDown(): void
    {
        if (file_exists(__DIR__ . "/testdata.log")) {
            unlink(__DIR__ . "/testdata.log");
        }
    }

    public function testProd()
    {
        list($server_res, $sdk_res) = $this->getClientInitializeResponses("https://statsigapi.net/v1");
        // $this->assertEquals(json_encode($server_res), json_encode($sdk_res));
    }

    public function testStaging()
    {
        list($server_res, $sdk_res) = $this->getClientInitializeResponses("https://staging.statsigapi.net/v1");
        // $this->assertEquals(json_encode($server_res), json_encode($sdk_res));
        $sdk_res_file = fopen("sdk_res.json", "w");
        fwrite($sdk_res_file, json_encode($sdk_res));
        fclose($sdk_res_file);
        $server_res_file = fopen("server_res.json", "w");
        fwrite($server_res_file, json_encode($server_res));
        fclose($server_res_file);
    }

    private function getClientInitializeResponses(string $api)
    {
        $curl = curl_init();
        $current_time = floor(microtime(true) * 1000);
        $input = json_encode(array(
            "user" => $this->user->toLogDictionary(),
            "statsigMetadata" => array(
                "sdkType" => "consistency-test",
                "sessionID" => "x123",
            ),
        ));
        curl_setopt_array($curl, array(
            CURLOPT_URL => "{$api}/initialize",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $input,
            CURLOPT_HTTPHEADER => array(
                "STATSIG-API-KEY: {$this->client_key}",
                "STATSIG-CLIENT_TIME: {$current_time}",
                'Content-Type: application/json'
            ),
        ));
        $res = curl_exec($curl);
        curl_close($curl);
        $server_response = (array) json_decode($res, true);
        $sdk_response = $this->statsig->getClientInitializeResponse($this->user);
        $this->postProcessResponse($server_response);
        $this->postProcessResponse($sdk_response);
        return array($server_response, $sdk_response);
    }

    private function sortMultidimensionalArrayByKey(array &$arr)
    {
        ksort($arr);
        foreach ($arr as $key => $val) {
            if (gettype($val) == "array") {
                $arr[$key] = $this->sortMultidimensionalArrayByKey($val);
            }
        }
        return $arr;
    }

    private function postProcessResponse(array &$response)
    {
        $this->sortMultidimensionalArrayByKey($response);
        foreach ($response["feature_gates"] as $key => $gate_response) {
            foreach ($gate_response["secondary_exposures"] ?? [] as $index => $se) {
                $response["feature_gates"][$key]["secondary_exposures"][$index]["gate"] = "__REMOVED_FOR_TEST__";
            }
        }
        foreach ($response["dynamic_configs"] as $key => $config_response) {
            foreach ($config_response["secondary_exposures"] ?? [] as $index => $se) {
                $response["dynamic_configs"][$key]["secondary_exposures"][$index]["gate"] = "__REMOVED_FOR_TEST__";
            }
        }
        foreach ($response["layer_configs"] as $key => $layer_response) {
            foreach ($layer_response["secondary_exposures"] ?? [] as $index => $se) {
                $response["layer_configs"][$key]["secondary_exposures"][$index]["gate"] = "__REMOVED_FOR_TEST__";
            }
            foreach ($layer_response["undelegated_secondary_exposures"] ?? [] as $index => $se) {
                $response["layer_configs"][$key]["undelegated_secondary_exposures"][$index]["gate"] = "__REMOVED_FOR_TEST__";
            }
        }
        $response["generator"] = "__REMOVED_FOR_TEST__";
        $response["time"] = 0;
    }
}
