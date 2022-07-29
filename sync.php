<?php

// Schedule a periodic cron job to sync config definitions with Statsig servers
// The SDK will use a file storing the definition of configs and will only be as up to date as that file

// USAGE:
// php sync.php --secret <STATSIG_SECRET_KEY>
//
// You may also provide your own custom adapter that implements IConfigAdapter
// php send.php --secret <STATSIG_SECRET_KEY> \
//     --adapter-class Namespace\For\MyConfigAdapter \
//     --adapter-path /path/to/MyConfigAdapter.php \
//     --adapter-arg an_argument_for_my_adapter \
//     --adapter-arg another_argument
//
// By default, send.php will use the Statsig LocalFileConfigAdapter which writes to /tmp/statsig.configs
// 
// Create a cron job that runs as statsigsync every minute
// $ echo '*/1 * * * * statsigsync php /my/path/to/statsig/sync.php > /dev/null' | sudo tee /etc/cron.d/statsigsync
// $ sudo service cron reload    # reload the cron daemon

require './vendor/autoload.php';

use Statsig\Adapters\IConfigAdapter;
use Statsig\ConfigSpecs;
use Statsig\CronJobUtils;
use Statsig\StatsigNetwork;


$args = CronJobUtils::getCommandLineArgs();

if (!isset($args['secret'])) {
    die('--secret must be given');
}

$adapter = CronJobUtils::getAdapter(
    $args['adapter-class'] ?? "Statsig\Adapters\LocalFileConfigAdapter",
    $args['adapter-path'] ?? "",
    $args['adapter-arg'] ?? [],
    IConfigAdapter::class
);

$network = new StatsigNetwork();
$network->setSdkKey($args['secret']);
$specs = $network->downloadConfigSpecs();

$parsed_gates = [];
for ($i = 0; $i < count($specs["feature_gates"]); $i++) {
    $parsed_gates[$specs["feature_gates"][$i]["name"]] = $specs["feature_gates"][$i];
}

$parsed_configs = [];
for ($i = 0; $i < count($specs["dynamic_configs"]); $i++) {
    $parsed_configs[$specs["dynamic_configs"][$i]["name"]] = $specs["dynamic_configs"][$i];
}

$specs = new ConfigSpecs();
$specs->gates = $parsed_gates;
$specs->configs = $parsed_configs;
$adapter->updateConfigSpecs($specs);
