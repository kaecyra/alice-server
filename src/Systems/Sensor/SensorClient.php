<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\Systems\Sensor;

use Alice\Alice;

use Alice\Common\Event;

use Alice\Socket\SocketMessage;
use Alice\Server\SocketClient;

/**
 * ALICE UI Sensor instance
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
class SensorClient extends SocketClient {

    const SENSOR_MOTION = 'motion';
    const SENSOR_CAMERA = 'camera';
    const SENSOR_TEMPERATURE = 'temperature';
    const SENSOR_HUMIDITY = 'humidity';
    const SENSOR_SMOKE = 'smoke';
    const SENSOR_LIGHT = 'light';

    /**
     * Sensor settings (from client)
     * @var array
     */
    protected $settings;

    /**
     * Handle sensor registrations
     *
     * @param SocketMessage $message
     */
    public function message_register(SocketMessage $message) {
        $data = $message->getData();
        return $this->registerClient($data);
    }

    /**
     * Register sensor client
     *
     * @param array $data
     * @return boolean
     */
    protected function registerClient($data) {
        $this->rec(" sensor client registering");
        sleep(1);

        // Name
        $name = val('name', $data);
        $id = val('id', $data);
        if (!$name || !$id) {
            return $this->sendError("cannot register: name and id are both required");
        }
        $this->registerClientID($name, $id);
        $this->rec("  name: {$name}");

        // Settings
        $settings = val('settings', $data);
        if (!$settings) {
            return $this->sendError("cannot register: settings are required");
        }
        $this->settings = $settings;

        // Let the sensor know that registration was successful
        $this->sendMessage('registered');
    }

    /**
     * Handle sensor data
     *
     * @param SocketMessage $message
     */
    public function message_sensor(SocketMessage $message) {
        $sensed = $message->getData();
        $this->rec("sensor: {$sensed}");
    }

    /**
     * Get namespaced cache key
     *
     * @param string $key
     * @return string
     */
    protected function getCacheKey($key) {
        return sprintf($key, $this->name);
    }

    /**
     * Hook motion event
     *
     * Alice has detected motion, so handle it here by waking the mirror and
     * locking in our guaranteed wake period with 'dimafter'.
     *
     */
    public function motion() {

        $dimAfter = val('dimafter', $this->settings);
        $dimAt = time() + $dimAfter;
        apcu_store($this->getCacheKey(self::MIRROR_LOCKDIM), $dimAt, $dimAfter);

        // Got motion, wake the mirror
        $this->wake();

    }

    /**
     * Hook still event
     *
     * Alice has detected no motion, so the mirror must decide if that means we
     * need to dim the display. This is based on whether there is a still a
     * mutex keeping the display on, or not.
     *
     */
    public function still() {

        $dimLock = apcu_fetch($this->getCacheKey(self::MIRROR_LOCKDIM));
        if ($dimLock) {
            if ($dimLock > time()) {
                $lockedFor = $dimLock - time();
                $this->rec("still lockout, {$lockedFor}s left");
                return;
            }

            // Lock failed to remove via TTL, remove manually
            apcu_delete($this->getCacheKey(self::MIRROR_LOCKDIM));
        }

        // No motion for $dimAfter seconds, sleep
        $this->sleep();

    }

}