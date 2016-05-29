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
class Weather extends DataSource {

    const FREQUENCY = 60;

    /**
     * Constructor
     *
     * @param string $type
     * @param array $config
     */
    public function __construct($type, $config) {
        parent::__construct($type, $config);

        $this->frequency = self::FREQUENCY;

        $this->provides = [
            'weather'
        ];

        $this->requirements = [
            'base' => [
                'city',
                'units'
            ],
            'geo' => [
                'latitude',
                'longitude'
            ]
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
        switch ($filter) {
            case 'geo':
                $hashData = formatString("{city},{latitude},{longitude}", $config);
                $hash = substr(sha1($hashData), 0, 16);
                break;
        }
        return "{$this->type}/{$filter}-{$hash}";
    }

    /**
     * Fetch updated weather
     *
     * @param string $filter
     * @param array $config
     */
    public function fetch($filter, $config) {
        return \Alice\API\Weather::get($filter, $config, $this->config);
    }

}