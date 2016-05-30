<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\Systems\Mirror;

use Alice\Server\SocketServer;
use Alice\Systems\Mirror\MirrorClient;

use Ratchet\ConnectionInterface;

/**
 * ALICE MirrorServer event handler
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
class MirrorServer extends SocketServer {

    /**
     * Get MirrorClient object
     *
     * @param ConnectionInterface $conn
     * @return MirrorClient
     */
    public function getNewClient(ConnectionInterface $conn) {
        return new MirrorClient($conn);
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
        rec("[mirrorserver] ".$message, $level, $options);
    }

}