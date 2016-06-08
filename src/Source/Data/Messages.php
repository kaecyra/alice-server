<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\Source\Data;

use Alice\Alice;
use Alice\Source\DataSource;

use \ZMQ;
use \ZMQSocket;
use \ZMQContext;

/**
 * ALICE DataSource: Messages
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
class Messages extends DataSource {

    const FREQUENCY = 10;

    /**
     *
     * @var ZMQContext
     */
    protected $context;

    /**
     * ZeroMQ socket
     * @var ZMQSocket
     */
    protected $zero;

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
            $this->context = new ZMQContext();

            $zmqConfig = Alice::go()->config()->get('data.zero');
            $zmqDSN = "tcp://{$zmqConfig['host']}:{$zmqConfig['port']}";
            $this->rec(" dsn: {$zmqDSN}");

            // Receive socket
            $this->zero = new ZMQSocket($this->context, ZMQ::SOCKET_SUB);
            $this->zero->bind($zmqDSN);
            $this->zero->setSockOpt(ZMQ::SOCKOPT_SUBSCRIBE, 'sensor-messages:');

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
     * Fetch messages
     *
     * @param string $filter
     * @param array $config
     */
    public function fetch($filter, $config) {

        $this->rec("gathering queued messages");
        $queued = [];
        do {
            try {
                $message = $this->zero->recv(ZMQ::MODE_DONTWAIT);
                if ($message) {
                    $this->rec(" {$message}");
                    $queued[] = $message;
                } else {
                    break;
                }
            }  catch (ZMQSocketException $e) {
                break;
            }
        } while (true);

        $nM = count($queued);
        $this->rec("gathered {$nM} queued messages");

        // Message upkeep

        $messages = [];

        return [
            'count' => count($messages),
            'messages' => $messages
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