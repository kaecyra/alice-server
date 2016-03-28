<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\Server;

class Sockets extends \Ratchet\App {

    public function __construct($httpHost = 'localhost', $port = 8080, $address = '127.0.0.1', \React\EventLoop\LoopInterface $loop = null) {
        rec("  new listener: {$httpHost}:{$port} ({$address})");
        parent::__construct($httpHost, $port, $address, $loop);

        rec("   route: /ui -> UI");
        $this->route('/ui', new UI);

        rec("   route: /hardware -> Hardware");
        $this->route('/hardware', new Hardware);
    }

}