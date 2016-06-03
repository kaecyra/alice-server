<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\Source;

use Alice\Common\Event;

use Exception;

/**
 * ALICE SensorSource Base
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
abstract class SensorSource extends Source {

    /**
     * SensorSource constructor
     *
     * No direct SensorSource instantiation is permitted, must be load()ed.
     *
     * @param string $type
     * @param array $config
     */
    protected function __construct($type, $config) {
        parent::__construct($type, $config);
        $this->id = val('id', $config);
        $this->class = Source::CLASS_SENSOR;
    }

    /**
     * Create configured SensorSource instance
     *
     * @param string $type
     * @param array $config
     * @return \Alice\Source\SensorSource
     * @throws Exception
     */
    public static function load($type, $config) {
        $ns = "\\Alice\\Source\\Sensor";
        $feature = ucfirst($type);
        $full = "{$ns}\\{$feature}";
        if (!class_exists($full)) {
            throw new Exception("Unknown feature: {$type} ({$full})");
        }

        return new $full($type, $config);
    }

    /**
     * Get SensorSource type
     *
     * @return string
     */
    public function getType() {
        return $this->type;
    }

    /**
     * Get SensorSource ID
     *
     * @return string
     */
    public function getID() {
        return $this->id;
    }

    /**
     * Get default SensorSource ID
     *
     * @return string
     */
    protected function buildID() {
        return val('id', $this->config);
    }

    /**
     * Check if this SensorSource can satisfy a filter
     *
     * @param string $filter
     * @return boolean
     */
    public function canSatisfy($filter) {
        if (in_array($filter, $this->satisfies)) {
            return true;
        }
        return false;
    }

    /**
     * Deploy sensor data
     *
     * @param mixed $data
     */
    abstract public function pushData($data);

    /**
     * Get event ID
     *
     * @return string
     */
    public function getEventID() {
        return "dataevent-{$this->class}:{$this->type}/{$this->id}";
    }

    /**
     * Record source specific mesage
     *
     * @param mixed $message
     * @param integer $level
     * @param integer $options
     */
    public function rec($message, $level = \Alice\Daemon\Daemon::LOG_L_APP, $options = \Alice\Daemon\Daemon::LOG_O_SHOWTIME) {
        if (is_array($message) || is_object($message)) {
            $message = print_r($message, true);
        }
        rec(sprintf("[sensorsource: %s] %s", $this->getID(), $message), $level, $options);
    }

    /**
     * Build DataWant ID from DataWant config
     *
     * @param string $filter
     * @param array $config
     * @return string
     */
    abstract protected function buildWantID($filter, $config);

}