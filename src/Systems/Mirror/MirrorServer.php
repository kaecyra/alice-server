<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\Server;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

use Alice\Server\Mirror;

/**
 * ALICE UI Server event handler
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
class Server implements MessageComponentInterface {

    /**
     *
     * @var \SplObjectStorage
     */
    protected $mirrors;

    /**
     *
     * @var \SplObjectStorage
     */
    protected $clients;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }

    /**
     * Get a mirror instance
     *
     * @param ConnectionInterface $conn
     * @return boolean
     */
    public function getMirror(ConnectionInterface $conn) {
        if ($this->clients->contains($conn)) {
            return $this->clients[$conn];
        }
        return false;
    }

    /**
     * Handle new connections from mirror UI clients
     *
     * @param ConnectionInterface $conn
     */
    public function onOpen(ConnectionInterface $conn) {
        rec("ui client connected");
        rec(" address: {$conn->remoteAddress}");

        $mirror = new Mirror($conn);
        $this->clients->attach($conn, $mirror);
    }

    /**
     * Handle messages from mirror UI clients
     *
     * @param ConnectionInterface $from
     * @param string $msg
     * @return type
     */
    public function onMessage(ConnectionInterface $from, $msg) {
        $mirror = $this->getMirror($from);
        if (!$mirror) {
            rec("message from unknown client, discarded");
            rec($msg);
            return;
        }

        try {
            $message = Message::parse($msg);
            $mirror->sent($message);
        } catch (\Exception $ex) {
            rec("msg handling error: ".$ex->getMessage());
            return false;
        }
    }

    /**
     * Handle connections being closed by mirror UI clients
     *
     * @param ConnectionInterface $conn
     */
    public function onClose(ConnectionInterface $conn) {
        rec("ui client disconnected");

        // Gracefully shut down mirror
        $mirror = $this->getMirror($conn);
        if ($mirror) {
            $mirror->shutdown();
            unset($mirror);
        }

        // Discard connection
        $this->clients->detach($conn);
    }

    /**
     * Handle errors from mirror UI clients
     *
     * @param ConnectionInterface $conn
     * @param \Exception $ex
     */
    public function onError(ConnectionInterface $conn, \Exception $ex) {
        rec("ui client error: ".$ex->getMessage());
        $conn->close();
    }

}