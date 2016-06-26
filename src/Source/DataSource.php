<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\Source;

use Exception;

/**
 * ALICE DataSource Base
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
abstract class DataSource extends Source {

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
     * Wake on next fetch
     * @var boolean
     */
    protected $wakeNext = false;

    /**
     * DataSource constructor
     *
     * No direct DataSource instantiation is permitted, must be load()ed.
     *
     * @param string $type
     * @param array $config
     */
    protected function __construct($type, $config) {
        parent::__construct($type, $config);
        $this->class = Source::CLASS_DATA;

        $this->satisfies = val('satisfies', $config);
        $this->frequency = 30;
    }

    /**
     * Create configured DataSource instance
     *
     * @param string $type
     * @param array $config
     * @return \Alice\Source\DataSource
     * @throws Exception
     */
    public static function load($type, $config) {
        $ns = "\\Alice\\Source\\Data";
        $feature = ucfirst($type);
        $full = "{$ns}\\{$feature}";
        if (!class_exists($full)) {
            throw new Exception("Unknown feature: {$type} ({$full})");
        }

        return new $full($type, $config);
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
     * Set wake on next fetch
     *
     * @param boolean $wake
     */
    public function setWake($wake) {
        $this->wakeNext = $wake;
    }

    /**
     * Get wake on next fetch
     *
     * @param boolean $clear set wakeNext to false when we query
     * @return boolean
     */
    public function getWake($clear = true) {
        return $this->wakeNext;
    }

    /**
     * Get wake on next fetch
     *
     * Also set wake state to false.
     *
     * @return boolean
     */
    public function popWake() {
        $wake = $this->wakeNext;
        $this->wakeNext = false;
        return $wake;
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