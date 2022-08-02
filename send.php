<?php

// Schedule a periodic cron job to send events to Statsig servers
// The SDK will write events to a file which will be uploaded by send.php

// USAGE:
// php send.php --secret <STATSIG_SECRET_KEY>
//
// You may also provide your own custom adapter that implements ILoggingAdapter
// php send.php --secret <STATSIG_SECRET_KEY> \
//    --adapter-class Namespace\For\MyLoggingAdapter \
//    --adapter-path /path/to/MyLoggingAdapter.php \
//    --adapter-arg an_argument_for_my_adapter \
//    --adapter-arg another_argument
//
// By default, send.php will use the Statsig LocalFileLoggingAdapter which writes to /tmp/statsig.logs
//
// Create a cron job that runs as statsigdata every minute
// $ echo '*/1 * * * * statsigdata php /my/path/to/statsig/send.php > /dev/null' | sudo tee /etc/cron.d/statsigdata
// $ sudo service cron reload    # reload the cron daemon

require './vendor/autoload.php';

use Statsig\Adapters\AdapterUtils;
use Statsig\Adapters\ILoggingAdapter;
use Statsig\StatsigNetwork;

$args = AdapterUtils::getCommandLineArgs();

if (!isset($args['secret'])) {
    die('--secret must be given');
}

$adapter = AdapterUtils::getAdapter(
    $args['adapter-class'] ?? "Statsig\Adapters\LocalFileLoggingAdapter",
    $args['adapter-path'] ?? "",
    $args['adapter-arg'] ?? [],
    ILoggingAdapter::class
);

$network = new StatsigNetwork();
$network->setSdkKey($args['secret']);
$events = $adapter->getQueuedEvents();

$total = count($events);
while (!empty($events)) {
    $to_send = array_slice($events, 0, 500);
    $events = array_slice($events, 500);
    $network->logEvents($to_send);
}

print("sent $total events\n");
exit(0);
