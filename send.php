<?php

// Schedule a periodic cron job to send events to Statsig servers
// The SDK will write events to a file which will be uploaded by send.php

// USAGE:
// php send.php --secret <STATSIG_SECRET_KEY>
//
// You may also provide your own custom adapter that implements ILoggingAdapter
// php send.php --secret <STATSIG_SECRET_KEY> --adapter Namespace\For\MyLoggingAdapter --adapter-arg an_argument_for_my_adapter --adapter-arg another_argument
//
// By default, send.php will use the Statsig LocalFileLoggingAdapter which writes to /tmp/statsig.logs
//
// Create a cron job that runs as statsigdata every minute
// $ echo '*/1 * * * * statsigdata php /my/path/to/statsig/send.php > /dev/null' | sudo tee /etc/cron.d/statsigdata
// $ sudo service cron reload    # reload the cron daemon

require './vendor/autoload.php';

use Statsig\Adapters\ILoggingAdapter;
use Statsig\CronJobUtils;
use Statsig\StatsigNetwork;

$version = "0.3.1";

$long_options = ["secret:", "adapter:", "adapter-arg:"];
$options = getopt("", $long_options);

if (!isset($options['secret'])) {
    die('--secret must be given');
}

$adapter = CronJobUtils::getAdapter(
    $options['adapter'] ?? "Statsig\Adapters\LocalFileLoggingAdapter",
    $options['adapter-arg'],
    ILoggingAdapter::class
);

$network = new StatsigNetwork($version);
$network->setSdkKey($options['secret']);
$events = $adapter->getQueuedEvents();

$total = count($events);
while (!empty($events)) {
    $to_send = array_slice($events, 0, 500);
    $events = array_slice($events, 500);
    $network->logEvents($to_send);
}

print("sent $total events\n");
exit(0);

