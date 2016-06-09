<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\Source\Data;

use Alice\Alice;
use Alice\Source\DataSource;

use \ZMQ;

/**
 * ALICE DataSource: Messages
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
class Messages extends DataSource {

    const FREQUENCY = 60;

    /**
     *
     * @var ZMQContext
     */
    protected $context;

    /**
     * ZeroMQ data socket
     * @var \ZMQSocket
     */
    protected $zero;

    /**
     * ZeroMQ sync socket
     * @var \ZMQSocket
     */
    protected $zerosync;

    /**
     * List of currently cached messages
     * @var array
     */
    protected $messages;

    /**
     * Constructor
     *
     * @param string $type
     * @param array $config
     */
    public function __construct($type, $config) {
        parent::__construct($type, $config);

        $this->frequency = self::FREQUENCY;

        $this->provides = [
            'messages'
        ];

        $this->requirements = [
            'base' => []
        ];

        $this->messages = [];

        try {
            $this->rec("binding zero socket");
            $this->context = new \React\ZMQ\Context(Alice::loop());

            $zmqConfig = Alice::go()->config()->get('data.zero');

            $zmqDataDSN = "tcp://{$zmqConfig['host']}:{$zmqConfig['port']}";
            $this->rec(" data dsn: {$zmqDataDSN}");

            $zmqSyncDSN = "tcp://{$zmqConfig['host']}:{$zmqConfig['syncport']}";
            $this->rec(" sync dsn: {$zmqSyncDSN}");

            // Bind receive socket
            $this->zero = $this->context->getSocket(ZMQ::SOCKET_SUB);
            $this->zero->bind($zmqDataDSN);
            $this->zero->subscribe('sensor-messages:');
            $this->zero->on('message', [$this, 'getMessage']);

            // Bind sync socket
            $this->zerosync = $this->context->getSocket(ZMQ::SOCKET_REP);
            $this->zerosync->bind($zmqSyncDSN);
            $this->zerosync->on('message', [$this, 'syncMessage']);

            $this->zero->on('error', function ($e) {
                $this->rec($e);
            });

        } catch (Exception $ex) {
            $this->rec(print_r($ex, true));
        }
    }

    /**
     * Build DataWant ID
     *
     * @param string $filter
     * @param array $config
     * @return string
     */
    public function buildWantID($filter, $config) {
        return "{$this->class}:{$this->type}/{$filter}";
    }

    /**
     * Inbound ZMQ message
     *
     * @param string $message
     */
    public function getMessage($message) {
        $this->rec("received message: {$message}");
    }

    /**
     * Inbound ZMQ sync message
     *
     * @param string $message
     */
    public function syncMessage($message) {
        $this->rec("received sync: {$message}");
        $this->zerosync->send("synced");
    }

    /**
     * Fetch messages
     *
     * @param string $filter
     * @param array $config
     */
    public function fetch($filter, $config) {

        return [
            'count' => count($this->messages),
            'messages' => $this->messages
        ];
    }

    /**
     * Get namespaced cache key
     *
     * @param string $key
     * @return string
     */
    protected function getCacheKey($key) {
        return sprintf($key, $this->name);
    }

}