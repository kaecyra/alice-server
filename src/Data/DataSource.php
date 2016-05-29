<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\Data;

use Exception;

/**
 * ALICE DataSource Base
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
abstract class DataSource {

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
     * DataSource satisfies
     * @var array
     */
    protected $satisfies;

    /**
     * Filter requirements
     * @var array
     */
    protected $requirements;

    /**
     * How many seconds between queries
     * @var integer
     */
    protected $frequency;

    /**
     * DataSource constructor
     *
     * No direct DataSource instantiation is permitted, must be load()ed.
     *
     * @param array $config
     */
    protected function __construct($type, $config) {
        $this->type = $type;
        $this->config = $config;
        $this->satisfies = val('satisfies', $config);
        $this->frequency = 30;

        $this->id = $this->buildID();
    }

    /**
     * Create configured DataSource instance
     *
     * @param string $type
     * @param array $config
     * @return \Alice\Data\DataSource
     * @throws Exception
     */
    public static function load($type, $config) {
        $ns = "\Alice\Data\Source";
        $feature = ucfirst($type);
        $full = "{$ns}\\{$feature}";
        if (!class_exists($full)) {
            throw new Exception("Unknown feature: {$type} ({$full})");
        }

        return new $full($type, $config);
    }

    /**
     * Get DataSource type
     *
     * @return string
     */
    public function getType() {
        return $this->type;
    }

    /**
     * Get DataSource ID
     *
     * @return string
     */
    public function getID() {
        return $this->id;
    }

    /**
     * Get default DataSource ID
     *
     * @return string
     */
    protected function buildID() {
        $source = val('source', $this->config);
        return "{$this->type}-{$source}";
    }

    /**
     * Check if this DataSource can satisfy a filter
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
     * Get list of required fields for source filter
     *
     * @param string $filter
     * @return array
     */
    public function getRequiredFields($filter) {
        $requirements = [];
        if (array_key_exists('base', $this->requirements)) {
            $requirements = array_merge($requirements, $this->requirements['base']);
        }
        if (array_key_exists($filter, $this->requirements)) {
            $requirements = array_merge($requirements, $this->requirements[$filter]);
        }
        return array_unique($requirements);
    }

    /**
     * Get update frequency
     *
     * @return integer
     */
    public function getFrequency() {
        return $this->frequency;
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
        rec(sprintf("[datasource: %s] %s", $this->getID(), $message), $level, $options);
    }

    /**
     * Build DataWant ID from DataWant config
     *
     * @param string $filter
     * @param array $config
     * @return string
     */
    abstract protected function buildWantID($filter, $config);

    /**
     * Fetch update
     *
     * @param string $filter
     * @param array $config
     * @return array
     */
    abstract public function fetch($filter, $config);

}