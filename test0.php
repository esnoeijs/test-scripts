#!/usr/bin/env php
<?php

$args = $argv;
array_shift($args);

$host = array_shift($args);
$user = array_shift($args);
$pass = array_shift($args);

echo "host: {$host}".PHP_EOL;
echo "user: {$user}".PHP_EOL;
echo "pass: {$pass}".PHP_EOL;


$maxConnections  = 2;
$connections     = [];


while (true)
{
    // make connections till MaxConnections
    while (count($connections) < $maxConnections) {
        $connections[] = createConnection($host, $user, $pass);
    }

    // Fetch data for each made connection
    foreach ($connections as $connection) {
        fetchData($connection);
    }

    stream_set_blocking(STDIN, false);
    $input = fgets(STDIN);


    if ($input !== false && $input >= 0) {
        echo "Setting max connections $maxConnections to {$input}".PHP_EOL;
        $maxConnections = (int) $input;
    }

    // Close all connections
    while (count($connections) > 0) {
        $link = array_shift($connections);
        ob_start();
        var_dump($link);
        $resourceId = trim(ob_get_clean());
        echo sprintf("[%s] Closed connection %s".PHP_EOL, date('H:i'), $resourceId);
        mysql_close($connections);
    }

    usleep(1000000);
}


function createConnection($host, $user, $pass)
{
    $start = microtime(true);
    $link  = mysql_connect($host, $user, $pass, true);
    $diff  = microtime(true) - $start;

    if (!$link) {
        echo sprintf("[%s][%f0] Could not connect %s".PHP_EOL, date('H:i'), $diff, mysql_error());
    } else {
        ob_start();
        var_dump($link);
        $resourceId = trim(ob_get_clean());
        echo sprintf("[%s][%f0] Connected %s".PHP_EOL, date('H:i'), $diff, $resourceId);
    }

    return $link;
}

/**
 * @param $connection
 */
function fetchData($connection)
{
    $start    = microtime(true);
    $resource = mysql_query("SELECT REPEAT(md5(floor(rand() * 10)), 1024) as data;", $connection);
    $data     = mysql_fetch_assoc($resource);
    $diff     = microtime(true) - $start;

    if (!$resource) {
        echo sprintf("[%s][%f0] error %s".PHP_EOL, date('H:i'), $diff, mysql_error());
    } else {
        ob_start();
        var_dump($connection);
        $resourceId = trim(ob_get_clean());
        echo sprintf("[%s][%f0] got data %s  length: %s data: %s".PHP_EOL, date('H:i'), $diff, $resourceId,  strlen($data['data']), substr($data['data'], 0, 16));
    }
}