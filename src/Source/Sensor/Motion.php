<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\Source\Sensor;

use Alice\Source\SensorSource;

/**
 * ALICE Feature
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
class Motion extends SensorSource {

    /**
     * Constructor
     *
     * @param string $type
     * @param array $config
     */
    public function __construct($type, $config) {
        parent::__construct($type, $config);

        $this->satisfies = [
            'motion'
        ];
    }

    /**
     * Build DataWant ID
     *
     * @param string $filter
     * @param array $config
     * @return string
     */
    public function buildWantID($filter, $config) {
        return "{$this->class}:{$this->type}/{$this->id}";
    }

    /**
     * Get data
     * 
     * @param string $data
     */
    public function pushData($data) {
        Event::fire($this->getEventID(), [$this->type, $this->id, $data]);
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

}