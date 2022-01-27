<?php

// Schedule a periodic cron job to send events to Statsig servers
// The SDK will write events to a file which will be uploaded by send.php

// USAGE:
// php send.php --secret <STATSIG_SECRET_KEY> --file /tmp/statsig.log
// 
// Create a cron job that runs as statsigdata every minute
// $ echo '*/1 * * * * statsigdata php /my/path/to/statsig/send.php > /dev/null' | sudo tee /etc/cron.d/statsigdata
// $ sudo service cron reload    # reload the cron daemon

require './vendor/autoload.php';

require dirname(__FILE__).'/src/statsig_network.php';

use Statsig\StatsigNetwork;

$version = "0.1.0";

$args = parse($argv);
if (!isset($args['secret'])) {
    die('--secret must be given');
}
if (!isset($args['file'])) {
    die('--file must be given');
}

$file = $args['file'];
if ($file[0] !== '/') {
    $file = __DIR__ . '/' . $file;
}

// Rename the file
$dir = dirname($file);
$old = $file;
$file = $dir . '/statsig-' . mt_rand() . '.log';

if (!file_exists($old)) {
    print("file: $old does not exist");
    exit(0);
}

if (!rename($old, $file)) {
    print("error renaming from $old to $file\n");
    exit(1);
}

$contents = file_get_contents($file);
$lines = explode("\n", $contents);

$network = new StatsigNetwork($version);
$network->setSdkKey($args['secret']);

$total = 0;
$events = [];
foreach ($lines as $line) {
    if (!trim($line)) {
        continue;
    }
    $events = array_merge($events, json_decode($line, true));
    if (count($events) > 500) {
        $to_send = array_slice($events, 0, 500);
        $events = array_slice($events, 500);
        $total += count($to_send);
        $network->log_events($to_send);
    }
}
$total += count($events);
$network->log_events($events);

unlink($file);

print("sent $total events\n");
exit(0);

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
