<?php

namespace Statsig\Test;

use PHPUnit\Framework\TestCase;
use Statsig\Evaluator;
use Statsig\StatsigServer;
use Statsig\StatsigNetwork;
use Statsig\StatsigUser;
use Statsig\StatsigOptions;
use Statsig\StatsigEvent;

class E2ETest extends TestCase {

    private $evaluator;
    private $cases;
    private $key;

    public function setUp() {
        $key = getenv("test_api_key");
        if (!$key || $key === null || strlen($key === 0)) {
            try {
                $key = file_get_contents(__DIR__.'/../../ops/secrets/prod_keys/statsig-rulesets-eval-consistency-test-secret.key');
            } catch (Exception $e) {
                throw new Exception("THIS TEST IS EXPECTED TO FAIL FOR NON-STATSIG EMPLOYEES! If this is the" +
                "only test failing, please proceed to submit a pull request. If you are a Statsig employee," +
                "chat with jkw.");
            }
        }
        $this->key = $key;
        $out = null;
        exec("php sync.php --secret ".$key." --output statsig.config 2>&1", $out);

        $net = new StatsigNetwork();
        $net->setSDKKey($key);
        $this->cases = $net->post_request('rulesets_e2e_test', json_encode((object)[]));
        
    }

    public function tearDown() {
        unlink("statsig.config");
    }

    public function testWithoutLogFile() {
        $options = new StatsigOptions("../statsig.config");
        $this->evaluator = new Evaluator($options);
        $this->statsig = new StatsigServer($this->key, $options);
        $this->helper();
        $this->statsig->flush();
    }

    public function testWithLogFile() {
        $options = new StatsigOptions("../statsig.config", "../statsig.log");
        $this->evaluator = new Evaluator($options);
        $this->statsig = new StatsigServer($this->key, $options);
        $this->helper();
        $out = null;
        // send.php will unlink the log file
        exec("php send.php --secret ".$this->key." --file statsig.log 2>&1", $out);
    }

    private function helper() {
        foreach ($this->cases as $entry) {
            foreach ($entry as $val) {
                $user = $val["user"];
                $statsig_user = new StatsigUser($user["userID"]);
                $statsig_user = $statsig_user
                    ->setAppVersion(array_key_exists("appVersion", $user) ? $user["appVersion"] : null)
                    ->setUserAgent(array_key_exists("userAgent", $user) ? $user["userAgent"] : null)
                    ->setIP(array_key_exists("ip", $user) ? $user["ip"] : null);
                if (array_key_exists("email", $user)) {
                    $statsig_user = $statsig_user->setEmail($user["email"]);
                }
                if (array_key_exists("statsigEnvironment", $user)) {
                    $statsig_user = $statsig_user->setStatsigEnvironment($user["statsigEnvironment"]);
                }
                if (array_key_exists("custom", $user)) {
                    $statsig_user = $statsig_user->setCustom($user["custom"]);
                }
                if (array_key_exists("privateAttributes", $user)) {
                    $statsig_user = $statsig_user->setPrivateAttributes($user["privateAttributes"]);
                }
                if (array_key_exists("customIDs", $user)) {
                    $statsig_user = $statsig_user->setCustomIDs($user["customIDs"]);
                }
                $event = new StatsigEvent("newevent");
                $event->setUser($statsig_user);
                $event->setValue(1337);
                $event->setMetadata(array("hello" => "world"));
                $this->statsig->logEvent($event);
                $gates = $val["feature_gates_v2"];
                foreach ($gates as $gate) {
                    $name = $gate["name"];
                    $this->statsig->checkGate($statsig_user, $name);
                    $eval_result = $this->evaluator->checkGate($statsig_user, $name);
                    $server_result = $gate["value"];
                    if ($name !== 'test_id_list' && $name !== 'test_not_in_id_list') {
                        $this->assertEquals($server_result, $eval_result->boolValue, "Failed value comparison for gate: ".$name);
                        $this->assertEquals($gate["rule_id"], $eval_result->ruleID);
                        $this->assertEquals($gate["secondary_exposures"], $eval_result->secondaryExposures);
                    }
                }

                $configs = $val["dynamic_configs"];
                foreach ($configs as $config) {
                    $name = $config["name"];
                    $this->statsig->getConfig($statsig_user, $name);
                    $eval_result = $this->evaluator->getConfig($statsig_user, $name);
                    $server_result = $config["value"];
                    $this->assertEquals($server_result, $eval_result->jsonValue);
                    $this->assertEquals($config["rule_id"], $eval_result->ruleID);
                    $this->assertEquals($config["secondary_exposures"], $eval_result->secondaryExposures);
                }
            }
        }
    }
}