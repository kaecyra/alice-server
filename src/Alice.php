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

use Exception;

/**
 * Daemon manager
 *
 * Abstracts forking-to-daemon logic away from application logic. Also handles
 * child pool management.
 *
 * @todo When forking, fork into two processes and use one as a clean control to
 * watch the other. When the worker ends, inspect the exit state and determine
 * whether to reload or fully quit and restart from cron.
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
class Alice implements App {

    protected $store;
    protected $config;

    static $alice = null;

    public function __construct() {
        rec(sprintf("%s (v%s)", APP, APP_VERSION), Daemon::LOG_L_APP, Daemon::LOG_O_SHOWTIME);
        self::$alice = $this;

        // Store
        rec(' preparing data store');
        $this->store = new Store;

        $appDir = Daemon::option('appDir');

        // Config
        rec(' reading config');
        $this->config = Config::file(paths($appDir, 'conf/config.json'), true);
    }

    /**
     * Get Jarvis reference
     *
     * @return Jarvis
     */
    public static function go() {
        return self::$jarvis;
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

        rec(' running');

        Event::fire('startup');

        // Enable periodic ticks
        Event::enableTicks();
    }

}