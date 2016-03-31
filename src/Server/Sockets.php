<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\Server;

/**
 * ALICE Websocket Listener
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
class Sockets extends \Ratchet\App {

    public function __construct($httpHost = 'localhost', $port = 8080, $address = '127.0.0.1', \React\EventLoop\LoopInterface $loop = null) {
        rec("  new listener: {$httpHost}:{$port} ({$address})");
        parent::__construct($httpHost, $port, $address, $loop);

        rec("   route: /alice -> Server");
        $this->route('/alice', new Server, ['*']);
    }

}