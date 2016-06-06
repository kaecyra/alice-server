<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

/*
 * ALICE http bootstrap
 *
 * @package alice-server
 */

define('APP', 'alice-server');
define('APP_VERSION', '1.0');
define('PATH_ROOT', getcwd());

if (PHP_VERSION_ID < 50400) {
    die(APP." requires PHP 5.4 or greater.");
}

// Report and track all errors.
error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR);
ini_set('display_errors', 0);
ini_set('track_errors', 1);

// Include composer autoloader
require __DIR__.'/vendor/autoload.php';

define('PATH_CONFIG', PATH_ROOT.'/conf');
define('PATH_JS', PATH_ROOT.'/js');
define('PATH_TEMPLATES', PATH_ROOT.'/templates');