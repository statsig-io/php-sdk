<?php

// Schedule a periodic cron job to sync config definitions with Statsig servers
// The SDK will use a file storing the definition of configs and will only be as up-to-date as that file

// USAGE:
// php sync.php --secret <STATSIG_SECRET_KEY>
//
// You may also provide your own custom adapter that implements IDataAdapter
// php sync.php --secret <STATSIG_SECRET_KEY> \
//     --adapter-class Namespace\For\MyDataAdapter \
//     --adapter-path /path/to/MyDataAdapter.php \
//     --adapter-arg an_argument_for_my_adapter \
//     --adapter-arg another_argument
//
// By default, sync.php will use the Statsig LocalFileDataAdapter which writes to /tmp/statsig
// 
// Create a cron job that runs as statsigsync every minute
// $ echo '*/1 * * * * statsigsync php /my/path/to/statsig/sync.php > /dev/null' | sudo tee /etc/cron.d/statsigsync
// $ sudo service cron reload    # reload the cron daemon

require './vendor/autoload.php';

use Statsig\Adapters\AdapterUtils;
use Statsig\Adapters\IDataAdapter;
use Statsig\ConfigSpecs;
use Statsig\IDList;
use Statsig\StatsigNetwork;

$args = AdapterUtils::getCommandLineArgs();

if (!isset($args['secret'])) {
    die('--secret must be given');
}

$adapter = AdapterUtils::getAdapter(
    $args['adapter-class'] ?? "Statsig\Adapters\LocalFileDataAdapter",
    $args['adapter-path'] ?? "",
    $args['adapter-arg'] ?? [],
    IDataAdapter::class
);

if (!($adapter instanceof IDataAdapter)) {
    print("Adapter class must be of type IDataAdapter");
    exit(1);
}

$network = new StatsigNetwork();
$network->setSdkKey($args['secret']);

ConfigSpecs::sync($adapter, $network);
IDList::sync($adapter, $network);
