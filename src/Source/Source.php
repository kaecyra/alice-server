<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\Source;

use Exception;

/**
 * ALICE Source Base
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
abstract class Source {

    const CLASS_DATA = 'data';
    const CLASS_SENSOR = 'sensor';

    /**
     * DataSource type
     * @var string
     */
    protected $type;

    /**
     * DataSource config
     * @var array
     */
    protected $config;

    /**
     * DataSource ID
     * @var string
     */
    protected $id;

    /**
     * SensorSource satisfies
     * @var array
     */
    protected $satisfies;

    /**
     * Source constructor
     *
     * No direct Source instantiation is permitted, must be load()ed.
     *
     * @param string $type
     * @param array $config
     */
    protected function __construct($type, $config) {
        $this->type = $type;
        $this->config = $config;
        $this->id = $this->buildID();
    }

    /**
     * Get Source type
     *
     * @return string
     */
    public function getType() {
        return $this->type;
    }

    /**
     * Get Source ID
     *
     * @return string
     */
    public function getID() {
        return $this->id;
    }

    /**
     * Get default Source ID
     *
     * @return string
     */
    protected function buildID() {
        $source = val('source', $this->config);
        return "{$this->type}-{$source}";
    }

    /**
     * Check if this Source can satisfy a filter
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
        rec(sprintf("[source: %s] %s", $this->getID(), $message), $level, $options);
    }
}