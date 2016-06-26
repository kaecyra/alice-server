<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\Server;

use Alice\Systems\Mirror\MirrorServer;
use Alice\Systems\Sensor\SensorServer;
use Alice\Systems\Output\OutputServer;

/**
 * ALICE Socket Dispatcher
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
class SocketDispatcher extends \Ratchet\App {

    public function __construct($httpHost = 'localhost', $port = 8080, $address = '127.0.0.1', \React\EventLoop\LoopInterface $loop = null) {
        rec("  new listener: {$httpHost}:{$port} ({$address})");
        parent::__construct($httpHost, $port, $address, $loop);

        // Add routing for supported apps

        rec("   route: /mirror -> MirrorServer");
        $this->route('/mirror', new MirrorServer, ['*']);

        rec("   route: /sensor -> SensorServer");
        $this->route('/sensor', new SensorServer, ['*']);

        rec("   route: /output -> OutputServer");
        $this->route('/output', new OutputServer, ['*']);
    }

}