<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\Server;

class Sockets extends \Ratchet\App {

    public function __construct($httpHost = 'localhost', $port = 8080, $address = '127.0.0.1', \React\EventLoop\LoopInterface $loop = null) {
        parent::__construct($httpHost, $port, $address, $loop);

        //$this->route('/ui', new UI);
        $this->route('/echo', new \Ratchet\Server\EchoServer, array('*'));
    }

}