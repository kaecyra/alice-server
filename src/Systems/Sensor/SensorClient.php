<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\Systems\Sensor;

use Alice\Alice;

use Alice\Source\Source;

use Alice\Socket\SocketMessage;
use Alice\Server\SocketClient;

/**
 * ALICE Sensor instance
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
    const SENSOR_AUDIO = 'audio';

    /**
     * Sensor settings (from client)
     * @var array
     */
    protected $settings;

    /**
     * Client source
     * @var Source
     */
    protected $source;

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

        // Create source
        $type = val('type', $data);
        $this->source = Alice::go()->aggregator()->loadSource(Source::CLASS_SENSOR, $type, $data);
        $this->source->attachClient($this);
        Alice::go()->aggregator()->addSource($this->source);

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
        $this->source->pushData($sensed);
    }

    /**
     * Handle sensor events
     *
     * @param SocketMessage $message
     */
    public function message_input(SocketMessage $message) {
        $event = $message->getData();
        $this->rec($event);
    }

    /**
     * Cleanup
     *
     */
    public function __destruct() {
        if ($this->source instanceof Source) {
            Alice::go()->aggregator()->removeSource($this->source);
        }
    }

}