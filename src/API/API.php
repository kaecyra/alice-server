<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\API;

use Alice\Alice;

/**
 * ALICE API Base
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
abstract class API {

    /**
     * Get API client instance
     *
     * @return Garden\Http\HttpClient
     */
    protected static function getClient() {
        return Alice::go()->aggregator()->getClient();
    }

    /**
     * Record API specific message
     *
     * @param mixed $message
     * @param integer $level
     * @param integer $options
     */
    public static function rec($message, $level = \Alice\Daemon\Daemon::LOG_L_APP, $options = \Alice\Daemon\Daemon::LOG_O_SHOWTIME) {
        if (is_array($message) || is_object($message)) {
            $message = print_r($message, true);
        }
        rec(sprintf("[api: %s] %s", static::API, $message), $level, $options);
    }

    /**
     * Get API data
     *
     * @param string $filter
     * @param array $connectorConfig
     * @param array $sourceDefinition
     */
    abstract public static function get($filter, $connectorConfig, $sourceDefinition);

}