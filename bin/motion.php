#!/usr/bin/env php
<?php

/**
 * Motion detector
 *
 * @version 1.0
 * @author Tim Gunter <tim@vanillaforums.com>
 * @license MIT
 * @copyright 2016 Tim Gunter
 * @package alice
 */

require '../vendor/autoload.php';

use PhpGpio\Gpio;

use Alice\Common\Config;

// Get Alice working directory
$pwd = dirname(__FILE__);
$pwd = preg_replace('`bin/?$`', '', $pwd);

$configFile = 'conf/config.json';
$configPath = paths($pwd, $configFile);
echo "loading config\n";
echo " file: {$configPath}\n";
$config = Config::app($configPath);

// Get memcached instance

echo "connecting to cache\n";
$cacheDSN = $config->get('cache');
echo " server: {$cacheDSN['server']}\n";
echo " port: {$cacheDSN['port']}\n";
$cache = new \Memcached();
$cache->addServer($cacheDSN['server'], $cacheDSN['port']);

// Set up GPIO

$gpio = new Gpio();
$gpio->unexportAll();

// Motion

echo "setting up motion gpio\n";
$motionPin = $config->get('motion.pin');
echo " pin: {$motionPin}\n";
$gpio->setup($motionPin, "in");

// LED

echo "setting up LED gpio\n";
$ledPin = $config->get('led.pin');
echo " pin: {$ledPin}\n";
$gpio->setup($ledPin, "out");

$gpio->output($ledPin, 0);
$lit = false;

// Event loop

$calibrateTime = $config->get('motion.calibrate');
if ($calibrateTime) {
    echo "PIR calibrating\n";
    echo " time: {$calibrateTime}s\n";
    sleep($calibrateTime);
}

echo "loop\n";
$delay = $config->get('loop.delay');
$delayS = round($delay / 1000000,1);
echo " delay: {$delay} ({$delayS}s)\n";

// Motion modifications
$coolFor = $config->get('motion.cool', 5);
$lightFor = $config->get('led.light', 0.5);

$waitUntil = 0;
$lightUntil = 0;
while (true) {
    $now = microtime(true);

    usleep($delay);

    if ($lit && $now > $lightUntil) {
        echo "  led off\n";
        $gpio->output($ledPin, 0);
        $lit = false;
        $lightUntil = 0;
    }

    // Wait to retrigger if we're cooling off
    if (microtime(true) < $waitUntil) {
        echo "  cooldown\n";
        continue;
    }

    $waitUntil = 0;

    // Test for motion
    $haveMotion = trim($gpio->input($motionPin));
    $txtMotion = $haveMotion ? 'yes' : 'no';
    echo " motion? {$txtMotion}\n";

    if ($haveMotion) {
        $waitUntil = microtime(true) + $coolFor;
        $lightUntil = microtime(true) + $lightFor;

        echo "  led on\n";
        $gpio->output($ledPin, 1);
        $lit = true;
    }

}

echo "unexport\n";
$gpio->unexportAll();