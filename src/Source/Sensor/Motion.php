<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\Source\Sensor;

use Alice\Source\SensorSource;

/**
 * ALICE SensorSource: Motion
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
     * Get namespaced cache key
     *
     * @param string $key
     * @return string
     */
    protected function getCacheKey($key) {
        return sprintf($key, $this->name);
    }

    /**
     * Tell remote client to hibernate
     * 
     */
    public function tellHibernate() {
        $this->getClient()->sendMessage('hibernate');
    }

    /**
     * Tell remote client to unhibernate
     *
     */
    public function tellUnhibernate() {
        $this->getClient()->sendMessage('unhibernate');
    }

}