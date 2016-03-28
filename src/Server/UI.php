<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\Server;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

use Alice\Server\UI\Message;

class UI implements MessageComponentInterface {

    protected $clients;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn) {
        rec("ui client connected");
        rec($conn);
        $this->clients->attach($conn);
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $message = new Message($from, $msg);
    }

    public function onClose(ConnectionInterface $conn) {
        rec("ui client disconnected");
        $this->clients->detach($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $ex) {
        rec("ui client error: ".$ex->getMessage());
        $conn->close();
    }

}