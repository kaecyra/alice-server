<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice;

use Alice\Daemon\App;
use Alice\Daemon\Daemon;

use Alice\Common\Config;
use Alice\Common\Store;
use Alice\Common\Event;

use Alice\Server\Sockets;

use Exception;

/**
 * ALICE Websocket Client
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
class Monitor implements App {

    protected $store;
    protected $config;

    /**
     *
     * @var Alice\Server\Sockets
     */
    protected $sockets;

    /**
     *
     * @var Alice\Monitor
     */
    static $server = null;

    public function __construct() {
        rec(sprintf("%s (v%s)", APP, APP_VERSION), Daemon::LOG_L_APP, Daemon::LOG_O_SHOWTIME);
        self::$server = $this;

        // Store
        rec(' preparing data store');
        $this->store = new Store;

        $appDir = Daemon::option('appDir');

        // Config
        rec(' reading config');
        $this->config = Config::file(paths($appDir, 'conf/config.json'), true);
    }

    /**
     * Get Alice Server reference
     *
     * @return Alice\Monitor
     */
    public static function go() {
        return self::$server;
    }

    /**
     * Execute main app payload
     *
     * @return string
     */
    public function run() {

        rec(' startup');

        $appDir = Daemon::option('appDir');
        $this->store->set('dir', $appDir);

        Event::fire('startup');

        // Enable periodic ticks
        Event::enableTicks();

        rec(' starting client');


        rec(' client closed');

    }

}