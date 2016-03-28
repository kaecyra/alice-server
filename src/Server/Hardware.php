<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\Server;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class Hardware implements MessageComponentInterface {

    protected $clients;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn) {
        rec(" hardware client connected");
        rec($conn);
        $this->clients->attach($conn);
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        rec();
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $ex) {
        $conn->close();
    }

}