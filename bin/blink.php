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

require 'vendor/autoload.php';

use PhpGpio\Gpio;

$gpio = new GPIO();

$pin = 11;
$gpio->setup($pin, "out");

while (true){
    echo "pin {$pin} HIGH\n";
    $gpio->output($pin, 1);
    sleep(1);

    echo "pin {$pin} LOW\n";
    $gpio->output($pin, 0);
    sleep(1);
}
