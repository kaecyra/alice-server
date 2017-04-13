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
use Alice\Common\Store;

use Alice\ModuleManager;

use Alice\Server\SocketDispatcher;

use Alice\Source\Aggregator;
use Alice\Source\Source;

use Alice\Command\Dispatcher;

use React\EventLoop\Factory as LoopFactory;

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
     * Socket Dispatcher
     * @var \Alice\Server\SocketDispatcher
     */
    protected $sockets;

    /**
     * Data aggregator
     * @var \Alice\Source\Aggregator
     */
    protected $aggregator;

    /**
     * Command dispatcher
     * @var \Alice\Command\Dispatcher
     */
    protected $dispatcher;

    /**
     * Module manager
     * @var \Alice\ModuleManager
     */
    protected $modules;

    /**
     * Data store
     * @var \Alice\Common\Store
     */
    protected $store;

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

        // Store
        $this->store = new Store();

        // Config
        rec(' reading config');
        $this->config = Config::file(paths($appDir, 'conf/config.json'), true);

        // Modules
        rec(' starting module manager');
        $this->modules = new ModuleManager();
        $this->modules->start($this->config());
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

        // Add data sources to aggregator
        rec(' adding data sources');
        foreach ($this->config->get('data.sources') as $source) {
            $sourceType = val('type', $source);
            if (!$sourceType) {
                rec('  skipped source with no type');
                continue;
            }

            $dataSource = Aggregator::loadSource(Source::CLASS_DATA, $sourceType, $source);
            if (!$dataSource) {
                rec("  unknown data source: {$sourceType}");
                continue;
            }

            $this->aggregator->addSource($dataSource);
            rec("  added source: ".$dataSource->getID());
        }

        // Add sensor sources to aggregator
        rec(' adding sensor sources');
        foreach ($this->config->get('data.sensors') as $source) {
            $sourceType = val('type', $source);
            if (!$sourceType) {
                rec('  skipped source with no type');
                continue;
            }

            $sensorSource = Aggregator::loadSource(Source::CLASS_SENSOR, $sourceType, $source);
            if (!$sensorSource) {
                rec("  unknown sensor source: {$sourceType}");
                continue;
            }

            $this->aggregator->addSource($sensorSource);
            rec("  added source: ".$sensorSource->getID());
        }

        // Create command dispatcher
        $this->dispatcher = new Dispatcher();

        Event::fire('startup');

        rec(' starting listeners');

        // Run the server application
        $this->sockets = new SocketDispatcher($this->config->get('server.host'), $this->config->get('server.port'), $this->config->get('server.address'), self::$loop);
        $ran = $this->sockets->run();

        rec(' listeners closed');
        rec($ran);
    }

    /**
     * Get aggregator reference
     *
     * @return \Alice\Source\Aggregator
     */
    public function aggregator() {
        return $this->aggregator;
    }

    /**
     * Get dispatcher reference
     *
     * @return \Alice\Command\Dispatcher
     */
    public function dispatcher() {
        return $this->dispatcher;
    }

    /**
     * Get config reference
     *
     * @return Config
     */
    public function config() {
        return $this->config;
    }

    /**
     * Get module manager reference
     *
     * @return ModuleManager
     */
    public function modules() {
        return $this->modules;
    }

    /**
     * Get store reference
     * @return Store
     */
    public function getStore() {
        return $this->store;
    }

}