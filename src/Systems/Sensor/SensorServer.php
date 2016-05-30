<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\Systems\Sensor;

use Alice\Server\SocketServer;
use Alice\Systems\Sensor\SensorClient;

use Ratchet\ConnectionInterface;

/**
 * ALICE SensorServer event handler
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
class SensorServer extends SocketServer {

    /**
     * Get SensorClient object
     *
     * @param ConnectionInterface $conn
     * @return SensorClient
     */
    public function getNewClient(ConnectionInterface $conn) {
        return new SensorClient($conn);
    }

    /**
     * Record socket server mesage
     *
     * @param mixed $message
     * @param integer $level
     * @param integer $options
     */
    public function rec($message, $level = \Alice\Daemon\Daemon::LOG_L_APP, $options = \Alice\Daemon\Daemon::LOG_O_SHOWTIME) {
        if (is_array($message) || is_object($message)) {
            $message = print_r($message, true);
        }
        rec("[sensorserver] ".$message, $level, $options);
    }

}