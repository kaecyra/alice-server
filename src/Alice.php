<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice;

use Alice\Daemon\App;
use Alice\Daemon\Daemon;

use Alice\Common\Config;
use Alice\Common\Event;

use Alice\Server\SocketDispatcher;

use Alice\Data\Aggregator;

use React\EventLoop\Factory as LoopFactory;

use Exception;

/**
 * ALICE Smart Home Server Daemon
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
class Alice implements App {

    /**
     * ALICE Server config
     * @var \Alice\Common\Config
     */
    protected $config;

    /**
     * Data aggregator
     * @var \Alice\Data\Aggregator
     */
    protected $aggregator;

    /**
     * Socket Dispatcher
     * @var \Alice\Server\SocketDispatcher
     */
    protected $sockets;

    /**
     * Loop
     * @var \React\EventLoop\LoopInterface
     */
    static $loop;

    /**
     * ALICE Instance
     * @var \Alice\Alice
     */
    static $server = null;

    public function __construct() {
        rec(sprintf("%s (v%s)", APP, APP_VERSION), Daemon::LOG_L_APP, Daemon::LOG_O_SHOWTIME);
        self::$server = $this;

        $appDir = Daemon::option('appDir');

        // Config
        rec(' reading config');
        $this->config = Config::file(paths($appDir, 'conf/config.json'), true);
    }

    /**
     * Get Alice Server reference
     *
     * @return \Alice\Alice
     */
    public static function go() {
        return self::$server;
    }

    /**
     * Get loop reference
     *
     * @return \React\EventLoop\LoopInterface
     */
    public static function loop() {
        return self::$loop;
    }

    /**
     * Execute main app payload
     *
     * @return string
     */
    public function run() {

        rec(' startup');

        // Start the loop
        self::$loop = LoopFactory::create();

        // Prepare Aggregator
        $this->aggregator = new Aggregator;

        $client = $this->config->get('data.client');
        $this->aggregator->setClientConfiguration($client);

        // Add sources to aggregator
        rec(' adding data sources');
        foreach ($this->config->get('data.sources') as $source) {
            $sourceType = val('type', $source);
            if (!$sourceType) {
                rec('  skipped source with no type');
                continue;
            }

            $dataSource = Aggregator::loadSource($sourceType, $source);
            if (!$dataSource) {
                rec("  unknown data source: {$sourceType}");
                continue;
            }

            $this->aggregator->addSource($dataSource);
            rec("  added source: ".$dataSource->getID());
        }

        Event::fire('startup');

        rec(' starting listeners');

        // Run the server application
        $this->sockets = new SocketDispatcher($this->config->get('server.host'), $this->config->get('server.port'), $this->config->get('server.address'), self::$loop);
        $ran = $this->sockets->run();

        rec(' listeners closed');
        rec($ran);
    }

    /**
     * Get aggregator
     *
     * @return Alice\Data\Aggregator
     */
    public function aggregator() {
        return $this->aggregator;
    }
}