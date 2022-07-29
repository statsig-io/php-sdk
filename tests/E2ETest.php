<?php

namespace Statsig\Test;

use Exception;
use PHPUnit\Framework\TestCase;
use Statsig\Adapters\LocalFileConfigAdapter;
use Statsig\Adapters\LocalFileLoggingAdapter;
use Statsig\Evaluator;
use Statsig\StatsigServer;
use Statsig\StatsigNetwork;
use Statsig\StatsigUser;
use Statsig\StatsigOptions;
use Statsig\StatsigEvent;

class E2ETest extends TestCase
{
    private StatsigServer $statsig;
    private Evaluator $evaluator;
    private array $cases;
    private string $key;

    /**
     * @throws Exception when test_api_key is not set as an EnvironmentVariable
     */
    protected function setUp(): void
    {
        $key = getenv("test_api_key");
        if (!$key || strlen($key) === 0) {
            throw new Exception("THIS TEST IS EXPECTED TO FAIL FOR NON-STATSIG EMPLOYEES! If this is the" .
                "only test failing, please proceed to submit a pull request. If you are a Statsig employee," .
                "chat with jkw.");
        }
        $this->key = $key;
        $out = null;
        exec("php sync.php --secret " . $key . " --adapter-arg ../../statsig.config 2>&1", $out);

        $net = new StatsigNetwork();
        $net->setSDKKey($key);
        $this->cases = $net->postRequest('rulesets_e2e_test', json_encode((object)[]));
    }

    protected function tearDown(): void
    {
        unlink("statsig.config");
    }

    public function testWithoutLogAdapter()
    {
        $adapter = new LocalFileConfigAdapter("../../statsig.config");
        $options = new StatsigOptions($adapter);
        $this->evaluator = new Evaluator($options);
        $this->statsig = new StatsigServer($this->key, $options);
        $this->helper();
        $this->statsig->flush();
    }

    public function testWithLogAdapter()
    {
        $config_adapter = new LocalFileConfigAdapter("../../statsig.config");
        $logging_adapter = new LocalFileLoggingAdapter("../../statsig.log");
        $options = new StatsigOptions($config_adapter, $logging_adapter);
        $this->evaluator = new Evaluator($options);
        $this->statsig = new StatsigServer($this->key, $options);
        $this->helper();
        $out = null;
        // send.php will unlink the log file
        exec("php send.php --secret " . $this->key . " --adapter-arg ../../statsig.log 2>&1", $out);
    }

    private function helper()
    {
        foreach ($this->cases as $entry) {
            foreach ($entry as $val) {
                $user = $val["user"];
                $statsig_user = StatsigUser::withUserID($user["userID"]);
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
                        $this->assertEquals($server_result, $eval_result->bool_value, "Failed value comparison for gate: " . $name);
                        $this->assertEquals($gate["rule_id"], $eval_result->rule_id);
                        $this->assertEquals($gate["secondary_exposures"], $eval_result->secondary_exposures);
                    }
                }

                $configs = $val["dynamic_configs"];
                foreach ($configs as $config) {
                    $name = $config["name"];
                    $this->statsig->getConfig($statsig_user, $name);
                    $eval_result = $this->evaluator->getConfig($statsig_user, $name);
                    $server_result = $config["value"];
                    $this->assertEquals($server_result, $eval_result->json_value);
                    $this->assertEquals($config["rule_id"], $eval_result->rule_id);
                    $this->assertEquals($config["secondary_exposures"], $eval_result->secondary_exposures);
                }
            }
        }
    }
}
