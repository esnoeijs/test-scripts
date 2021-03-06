#!/usr/bin/env php
<?php

$args = $argv;
array_shift($args);

$host = array_shift($args);
$user = array_shift($args);
$pass = array_shift($args);
$db   = array_shift($args);

echo "host: {$host}".PHP_EOL;
echo "user: {$user}".PHP_EOL;
echo "pass: {$pass}".PHP_EOL;
echo "db: {$db}".PHP_EOL;


$maxConnections  = 2;
$connections     = [];
$dataMultiplier  = 10;
$childs = [];
$time = microtime(true);


if (!file_exists('queries.sql')) {
    echo "need queries.sql with queries".PHP_EOL;
    die();
}

$queries = file('queries.sql');

$tickDelay = 0;
while (true)
{
    ini_set('mysql.connect_timeout','5');

    $tickStep = (1 - $tickDelay);
    if ($time + $tickStep <= microtime(true)) {
        $childs = [];
        echo sprintf("[%s] tick %s  conn: %s connections/s: %s" . PHP_EOL, date('H:i:s'), $tickStep, $maxConnections, (1 / $tickStep) * $maxConnections);

        while (count($childs) < $maxConnections) {
            $pid = pcntl_fork();

            if ($pid === 0) {

                usleep(1000 * rand(0, 10));
                $link = createConnection($host, $user, $pass, $db);
                fetchData($link, $dataMultiplier, getQuery($queries));
                usleep(10000 * rand(0, 100));
                closeConnection($link);
                die();
            } else {
                $childs[] = $pid;
            }
        }

        foreach ($childs as $child) {
            pcntl_wait($status);
        }
        $time = microtime(true);
    }

    stream_set_blocking(STDIN, false);
    $input = trim(fgets(STDIN));

    if ($input !== false && $input == '+') {
        $dataMultiplier += 10;
        echo "Increased data multiplier $dataMultiplier".PHP_EOL;
    }
    if ($input !== false && $input == '-') {
        $dataMultiplier -= 10;
        if ($dataMultiplier < 0) $dataMultiplier = 1;
        echo "Decreased data multiplier $dataMultiplier".PHP_EOL;
    }
    if ($input !== false && $input == '[') {
        $tickDelay += 0.100;
        echo "Increased delay $tickDelay".PHP_EOL;
    }
    if ($input !== false && $input ==']') {
        $tickDelay -= 0.100;
        if ($tickDelay < 0) $tickDelay = 0;
        echo "Decreased delay $tickDelay".PHP_EOL;
    }
    if ($input !== false && $input > 0) {
        echo "Setting max connections $maxConnections to {$input}".PHP_EOL;
        $maxConnections = (int) $input;
    }
}


function createConnection($host, $user, $pass, $db)
{
    $start = microtime(true);
    $link  = mysql_connect($host, $user, $pass, true);
    $diff  = microtime(true) - $start;

    if (!$link) {
        echo sprintf("[%s][%f0] Could not connect %s  MySQL %s".PHP_EOL, date('H:i:s'), $diff, mysql_error(), mysql_thread_id($link));
    } else {
        mysql_select_db($db);
        ob_start();
        var_dump($link);
        $resourceId = trim(ob_get_clean());
//        echo sprintf("[%s][%f0] Connected %s MySQL %s".PHP_EOL, date('H:i:s'), $diff, $resourceId, mysql_thread_id($link));
    }

    return $link;
}

/**
 * @param $connection
 */
function fetchData($connection, $dataMultiplier, $query)
{
    $start    = microtime(true);
    $resource = mysql_query($query);
    $diff     = microtime(true) - $start;

    if (!$resource) {
        echo sprintf("[%s][%f0] error %s".PHP_EOL, date('H:i:s'), $diff, mysql_error());
    } else {
        $data     = mysql_fetch_assoc($resource);
        ob_start();
        var_dump($connection);
        $resourceId = trim(ob_get_clean());
//        echo sprintf("[%s][%f0] got data %s  length: %s data: %s".PHP_EOL, date('H:i:s'), $diff, $resourceId,  strlen($data['data']), substr($data['data'], 0, 16));
    }
}

/**
 * @param $link
 */
function closeConnection($link)
{
    ob_start();
    var_dump($link);
    $resourceId = trim(ob_get_clean());
//    echo sprintf("[%s] Closed connection %s" . PHP_EOL, date('H:i:s'), $resourceId);
    mysql_close($link);
}


function getQuery($queries)
{
    $count = count($queries);
    return $queries[rand(0, $count-1)];
}