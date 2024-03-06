<?php

namespace Statsig\Test;

use PHPUnit\Framework\TestCase;
use Statsig\OutputLoggers\IOutputLogger;
use Statsig\Adapters\LocalFileLoggingAdapter;
use Statsig\StatsigServer;
use Statsig\StatsigUser;
use Statsig\StatsigOptions;

class ConfigSpecSetupTest extends TestCase
{
  private array $dcs;
  private IOutputLogger $output_logger;
  private LocalFileLoggingAdapter $logging_adapter;
  private StatsigServer $statsig;
  private StatsigUser $statsigUser;

  protected function setup(): void
  {
    $this->dcs = json_decode(file_get_contents(__DIR__ . "/statsig.cache"), true);
    $this->logging_adapter = new LocalFileLoggingAdapter("../../tests/testdata.log");
    $this->output_logger = new TestOutputLogger();
    $this->statsigUser = StatsigUser::withUserID("123");
    $this->statsigUser->setEmail("testuser@statsig.com");
  }

  public function testEmptyDownloadConfigSpecs()
  {
    $this->setupHelper(false, false);
    $this->statsig->getLayer($this->statsigUser, "unallocated_layer");
    $this->statsig->checkGate($this->statsigUser, "always_on_gate");
    $this->statsig->getConfig($this->statsigUser, "test_config");
    $this->statsig->getExperiment($this->statsigUser, "test_config");
    $this->assertEquals(0, count($this->output_logger->warning_messages));
    $this->assertEquals(4, count($this->output_logger->error_messages));
    for ($i = 0; $i < 4; ++$i) {
      $this->assertEquals("[Statsig]: Cannot load config specs, falling back to default values: Check if sync.php run successfully", $this->output_logger->error_messages[$i]);
    }
  }

  public function testStaleDownloadConfigSpecs()
  {
    $this->setupHelper(true, true);
    $this->statsig->getLayer($this->statsigUser, "unallocated_layer");
    $this->statsig->checkGate($this->statsigUser, "always_on_gate");
    $this->statsig->getConfig($this->statsigUser, "test_config");
    $this->statsig->getExperiment($this->statsigUser, "test_config");
    $this->assertEquals(0, count($this->output_logger->error_messages));
    $this->assertEquals(4, count($this->output_logger->warning_messages));
    for ($i = 0; $i < 4; ++$i) {
      $this->assertStringMatchesFormat('[Statsig]: Config spec is possibly not up-to-date: last time polling config specs is UTC %s', $this->output_logger->warning_messages[$i]);
    }
  }

  public function testDownloadConfigSpecs()
  {
    $this->setupHelper();
    $this->statsig->getLayer($this->statsigUser, "unallocated_layer");
    $this->statsig->checkGate($this->statsigUser, "always_on_gate");
    $this->statsig->getConfig($this->statsigUser, "test_config");
    $this->statsig->getExperiment($this->statsigUser, "test_config");
    $this->assertEquals(0, count($this->output_logger->error_messages));
    $this->assertEquals(0, count($this->output_logger->warning_messages));
  }

  private function setupHelper(bool $shouldReturn = true, bool $returnStaleData = false)
  {
    $this->dcs["fetch_time"] = ($returnStaleData ? floor(microtime(true) * 1000 - 120000) : floor(microtime(true) * 1000));
    $mock_config_adapter = \Mockery::mock('Statsig\Adapters\LocalFileDataAdapter');
    $contents = $shouldReturn ? json_encode($this->dcs) : '';
    $mock_config_adapter->shouldReceive("get")->andReturn($contents);
    $this->statsig = new StatsigServer("secret-test", new StatsigOptions($mock_config_adapter, $this->logging_adapter, $this->output_logger));
  }
}

class TestOutputLogger implements IOutputLogger
{
  public array $warning_messages = [];
  public array $error_messages = [];

  public function error(string $message)
  {
    $this->error_messages[] = $message;
  }

  public function warning(string $message)
  {
    $this->warning_messages[] = $message;
  }
}
