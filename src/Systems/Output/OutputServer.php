<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\Systems\Output;

use Alice\Server\SocketServer;
use Alice\Systems\Output\OutputClient;

use Ratchet\ConnectionInterface;

/**
 * ALICE OutputServer event handler
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
class OutputServer extends SocketServer {

    /**
     * Get OutputClient object
     *
     * @param ConnectionInterface $conn
     * @return OutputClient
     */
    public function getNewClient(ConnectionInterface $conn) {
        return new OutputClient($conn);
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
        rec("[outputserver] ".$message, $level, $options);
    }

}