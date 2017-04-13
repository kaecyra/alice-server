<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\Server;

use Alice\Common\Event;

use Alice\Socket\SocketMessage;

use Ratchet\ConnectionInterface;

/**
 * ALICE Socket Client
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
abstract class SocketClient {

    /**
     * Ratchet connection
     * @var ConnectionInterface
     */
    protected $connection;

    /**
     * Array of known hooks
     * @var array
     */
    protected $hooks;

    /**
     * Unique Client ID
     * @var string
     */
    protected $uid = null;

    /**
     * Client name
     * @var string
     */
    protected $name;

    /**
     * Client ID
     * @var string
     */
    protected $id;

    /**
     * Client type
     * @var string
     */
    protected $type;

    /**
     *
     * @param \Alice\Systems\Mirror\ConnectionInterface $connection
     */
    public function __construct(ConnectionInterface $connection) {
        $this->uid = uniqid('client-');
        $this->id = $this->uid;
        $this->name = 'unknown client';
        $this->connection = $connection;
        $this->hooks = [];
    }

    /**
     * Register socket client
     *
     * @param string $name
     * @param string $id
     */
    public function registerClientID($name, $id) {
        $this->name = $name;
        $this->id = $id;
    }

    /**
     * Send a message to the client
     *
     * @param string $method
     * @param mixed $data
     */
    public function sendMessage($method, $data = null) {
        $message = SocketMessage::compile($method, $data);
        $this->connection->send($message);
    }

    /**
     * Send error
     *
     * Send an error message to the client and optionally disconnect.
     *
     * @param string $reason optional.
     * @param boolean $fatal optional. disconnect. default true.
     */
    public function sendError($reason = null, $fatal = true) {
        if ($reason) {
            $this->rec("client error: {$reason}");
            $this->sendMessage('error', [
                'reason' => $reason
            ]);
        } else {
            $this->rec("client error");
        }

        if ($fatal) {
            $this->connection->close();
        }
    }

    /**
     * Register a hook
     *
     * @param string $event
     * @param type $callback
     */
    public function hook($event, $callback) {
        $signature = Event::hook($event, $callback);
        $this->registerHook($event, $signature);
    }

    /**
     * Register a client hook
     *
     * @param string $event
     * @param string $signature
     */
    public function registerHook($event, $signature) {
        $this->rec("  registered hook for '{$event}' -> {$signature}");
        $this->hooks[$event] = $signature;
    }

    /**
     * Get list of events hooked by this client
     *
     * @return array
     */
    public function getHooks() {
        return $this->hooks;
    }

    /**
     * Shutdown client
     *
     * This method is called when the client disconnects from the server.
     */
    public function shutdown() {
        $hooks = $this->getHooks();

        // Remove hooks for this module
        foreach ($hooks as $event => $signature) {
            Event::unhook($event, $signature);
        }
    }

    /**
     * Record client specific mesage
     *
     * @param mixed $message
     * @param integer $level
     * @param integer $options
     */
    public function rec($message, $level = \Alice\Daemon\Daemon::LOG_L_APP, $options = \Alice\Daemon\Daemon::LOG_O_SHOWTIME) {
        if (is_array($message) || is_object($message)) {
            $message = print_r($message, true);
        }
        rec("[client: {$this->id}] ".$message, $level, $options);
    }

    /**
     * Handle incoming message from client
     *
     * @param SocketMessage $message
     */
    public function onMessage(SocketMessage $message) {
        // Route to message handler
        $call = 'message_'.$message->getMethod();
        if (is_callable([$this, $call])) {
            $this->$call($message);
        } else {
            $this->rec("received message: ".$message->getMethod());
            $this->rec(sprintf(" could not handle message: unknown type '{%s}'", $message->getMethod()));
        }
    }

}