<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\Data\Source;

use Alice\Data\DataSource;

/**
 * ALICE Feature
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
class Calendar extends DataSource {

    /**
     * Constructor
     *
     * @param string $type
     * @param array $config
     */
    public function __construct($type, $config) {
        parent::__construct($type, $config);

        $this->provides = [
            'calendar'
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
        $hashData = formatString("{city}", $config);
        $hash = substr(sha1($hashData), 0, 16);
        return "{$this->type}/{$filter}-{$hash}";
    }

    /**
     * Fetch updated calendar
     *
     * @param array $config
     */
    public function fetch($config) {
        return \Alice\API\Calendar::get($config, $this->config);
    }

}