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
class News extends DataSource {

    const FREQUENCY = 300;

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
            'news'
        ];

        $this->requirements = [
            'base' => [
                'limit',
                'width'
            ],
            'localnews' => [
                'city'
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
            case 'localnews':
                $hashData = formatString("{city}", $config);
                $hash = substr(sha1($hashData), 0, 16);
                break;

            case 'worldnews':
                $hash = "global";
                break;
        }
        return "{$this->type}/{$filter}-{$hash}";
    }

    /**
     * Fetch updated news
     *
     * @param string $filter
     * @param array $config
     */
    public function fetch($filter, $config) {
        return \Alice\API\News::get($filter, $config, $this->config);
    }

}