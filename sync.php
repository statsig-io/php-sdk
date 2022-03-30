<?php

// Schedule a periodic cron job to sync config definitions with Statsig servers
// The SDK will use a file storing the definition of configs and will only be as up to date as that file

// USAGE:
// php sync.php --secret <STATSIG_SECRET_KEY> --output /tmp/statsig.config
// 
// Create a cron job that runs as statsigsync every minute
// $ echo '*/1 * * * * statsigsync php /my/path/to/statsig/sync.php > /dev/null' | sudo tee /etc/cron.d/statsigsync
// $ sudo service cron reload    # reload the cron daemon

require './vendor/autoload.php';

use Statsig\StatsigNetwork;

$version = "0.2.0";

$args = parse($argv);
if (!isset($args['secret'])) {
    die('--secret must be given');
}
if (!isset($args['output'])) {
    die('--output must be given');
}

$file = $args['output'];
if ($file[0] !== '/') {
    $file = __DIR__ . '/' . $file;
}

$dir = dirname($file);
$temp = $dir . '/' . mt_rand() . basename($file);

$network = new StatsigNetwork($version);
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
$contents = (object)[];
$contents->gates = $parsed_gates;
$contents->configs = $parsed_configs;
file_put_contents($temp, json_encode($contents));

if (!rename($temp, $file)) {
    print("error renaming from $temp to $file\n");
    exit(1);
}

function parse($argv): array {
    $ret = [];

    foreach ($argv as $param => $value) {
        if (strpos($value, '--') !== 0) {
            continue;
        }
        $ret[substr($value, 2, strlen($value))] = trim($argv[++$param]);
    }

    return $ret;
}
