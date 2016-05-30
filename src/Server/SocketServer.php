<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\Server;

use Alice\Server\SocketClient;
use Alice\Socket\SocketMessage;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

/**
 * ALICE Socket Server
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
abstract class SocketServer implements MessageComponentInterface {

    /**
     * Array mapping of ConnectionInterface > SocketClient objects
     * @var \SplObjectStorage
     */
    protected $clients;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }

    /**
     * Get a SocketClient instance
     *
     * @param ConnectionInterface $connection
     * @return SocketClient|false
     */
    public function getClient(ConnectionInterface $connection) {
        if ($this->clients->contains($connection)) {
            return $this->clients[$connection];
        }
        return false;
    }

    /**
     * Handle new connections from mirror UI clients
     *
     * @param ConnectionInterface $conn
     */
    public function onOpen(ConnectionInterface $conn) {
        $this->rec("client connected");
        $this->rec(" address: {$conn->remoteAddress}");

        $client = $this->getNewClient($conn);
        $this->clients->attach($conn, $client);
    }

    /**
     * Handle messages from mirror UI clients
     *
     * @param ConnectionInterface $conn
     * @param string $msg
     * @return type
     */
    public function onMessage(ConnectionInterface $conn, $msg) {
        $client = $this->getClient($conn);
        if (!$client) {
            $this->rec("message from unknown socket client, discarded");
            $this->rec($msg);
            return;
        }

        try {
            $message = SocketMessage::parse($msg);
            $client->onMessage($message);
        } catch (\Exception $ex) {
            $this->rec("msg handling error: ".$ex->getMessage());
            return false;
        }
    }

    /**
     * Handle connections being closed by mirror UI clients
     *
     * @param ConnectionInterface $conn
     */
    public function onClose(ConnectionInterface $conn) {
        $this->rec("socket client disconnected");

        // Gracefully shut down client
        $client = $this->getClient($conn);
        if ($client) {
            $client->shutdown();
            unset($client);
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
        rec("socket client error: ".$ex->getMessage());
        $conn->close();
    }

    /**
     * Record socket mesage
     *
     * @param mixed $message
     * @param integer $level
     * @param integer $options
     */
    public function rec($message, $level = \Alice\Daemon\Daemon::LOG_L_APP, $options = \Alice\Daemon\Daemon::LOG_O_SHOWTIME) {
        if (is_array($message) || is_object($message)) {
            $message = print_r($message, true);
        }
        rec("[socket] ".$message, $level, $options);
    }

    /**
     * Get new custom client handler
     *
     * @param ConnectionInterface $conn
     * @return SocketClient
     */
    abstract public function getNewClient(ConnectionInterface $conn);

}