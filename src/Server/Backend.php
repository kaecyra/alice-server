<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\Server;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class UI implements MessageComponentInterface {

    protected $clients;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        foreach ($this->clients as $client) {
            if ($from != $client) {
                $client->send($msg);
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $ex) {
        $conn->close();
    }

}