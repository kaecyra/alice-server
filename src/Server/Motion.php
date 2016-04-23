<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\Server;

use PhpGpio\Gpio;

/**
 * ALICE Backend motion sensor
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
class Motion {

    /**
     * Config
     * @var array
     */
    protected $config;

    /**
     * GPIO instance
     * @var PhpGpio\Gpio
     */
    protected $gpio;

    /**
     * Motion sensor constructor
     *
     * @param array $config
     */
    public function __construct($config) {
        $this->config = $config;
        $this->prepareGPIO();
    }

    /**
     * Prepare GPIO
     *
     * Register GPIO pin directions and calibrate if needed
     */
    public function prepareGPIO() {
        rec("setting up gpio");
        $this->gpio = new Gpio();
        $this->gpio->unexportAll();

        rec(" motion");

        // Wire up pin
        $motionPin = val('pin', $this->config);
        rec("  pin: {$motionPin}");
        $this->gpio->setup($motionPin, "in");

        // Calibrate?
        $calibrateTime = val('calibrate', $this->config, false);
        if ($calibrateTime) {
            rec("  calibrating PIR, time: {$calibrateTime}s");
            sleep($calibrateTime);
        }
    }

    /**
     * Check for motion
     *
     * @return boolean
     */
    public function sense() {
        $motionPin = val('pin', $this->config);
        $sense = trim($this->gpio->input($motionPin));
        $motion = $sense ? true : false;
        return $motion;
    }

}