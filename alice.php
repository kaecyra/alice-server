#!/usr/bin/env php
<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice;

/**
 * alice.php is a websocket server for the ALICE mirror system.
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 * @version 1.0
 */

use \Alice\Daemon\Daemon;

chdir(dirname(__FILE__));
define('APP', 'alice-server');
define('APP_ROOT', getcwd());
date_default_timezone_set('UTC');

// Reflect on ourselves for the version
$matched = preg_match('`@version ([\w\d\.-]+)$`im', file_get_contents(__FILE__), $matches);
if (!$matched) {
    echo "Unable to read version\n";
    exit;
}
$version = $matches[1];
define('APP_VERSION', $version);

// Include the core autoloader.
require_once __DIR__.'/vendor/autoload.php';

$exitCode = 0;
try {

    Daemon::configure([
        'appVersion'        => APP_VERSION,
        'appDir'            => APP_ROOT,
        'appDescription'    => 'ALICE websocket server.',
        'appNamespace'      => 'Alice',
        'appName'           => 'Alice',
        'authorName'        => 'Tim Gunter',
        'authorEmail'       => 'tim@vanillaforums.com',
        'appConcurrent'     => false,
        'appLogLevel'       => Daemon::LOG_L_ALL,
        'appLogFile'        => 'log/alice.log',
        //'sysRunAsGroup'     => 'root',
        //'sysRunAsUser'      => 'root',
        'sysMode'           => Daemon::MODE_SINGLE,
        'sysDaemonize'      => false
    ]);

    $exitCode = Daemon::start($argv);

} catch (\Alice\Daemon\Exception $ex) {

    $exceptionCode = $ex->getCode();
    if ($exceptionCode != 200) {

        // Don't write to file for commandline errors
        if ($exceptionCode == 600) {
            $msgOptions |= Daemon::LOG_O_NOWRITE;
        }

        if ($ex->getFile()) {
            $line = $ex->getLine();
            $file = $ex->getFile();
            Daemon::log(Daemon::LOG_L_FATAL, "Error on line {$line} of {$file}:", $msgOptions);
        }
        Daemon::log(Daemon::LOG_L_FATAL, $ex->getMessage(), $msgOptions);
    }

} catch (\Exception $ex) {
    $exitCode = 1;
    $msgOptions = 0;

    if ($ex->getFile()) {
        $line = $ex->getLine();
        $file = $ex->getFile();
        Daemon::log(Daemon::LOG_L_FATAL, "Error on line {$line} of {$file}:", $msgOptions);
    }
    Daemon::log(Daemon::LOG_L_FATAL, $ex->getMessage(), $msgOptions);
}

exit($exitCode);
