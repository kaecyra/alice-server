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

}