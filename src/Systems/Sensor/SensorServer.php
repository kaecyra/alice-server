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

}