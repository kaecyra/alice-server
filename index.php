<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

/**
 * ALICE Application Gateway.
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */

namespace Alice\Http;

require 'bootstrap.php';

$config = [
    'settings' => [
        'displayErrorDetails' => true
    ],
];

$webui = new WebUI($config);
$webui->run();
