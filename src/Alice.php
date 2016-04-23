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

use Alice\API\News;
use Alice\API\Weather;

use React\EventLoop\Factory as LoopFactory;

use Garden\Http\HttpClient;

use Exception;

/**
 * ALICE Websocket Daemon
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
class Alice implements App {

    /**
     *
     * @var Alice\Common\Store
     */
    protected $store;

    /**
     *
     * @var Alice\Common\Config
     */
    protected $config;

    /**
     *
     * @var Alice\Server\Sockets
     */
    protected $sockets;

    /**
     *
     * @var Alice\Server
     */
    static $server = null;

    /**
     * List of frequencies
     * @var array
     */
    protected $frequencies = [
        'weather' => 600,
        'time' => 60,
        'news' => 600
    ];

    /**
     * List of timing arrays
     * @var array
     */
    protected $timing = [];

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

        // Motion
        $motionConfig = $this->config->get('monitor.motion');
        $this->motion = new Motion($motionConfig);

        // Hooks

        // Predefine timers as now
        $this->clearTimers();

        Event::hook('catchup', [$this, 'catchUp']);
        Event::hook('connected', [$this, 'mirrorConnected']);
    }

    /**
     * Get Alice Server reference
     *
     * @return Server
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

        $loop = LoopFactory::create();
        $loop->addPeriodicTimer(1, [$this, 'tick']);

        rec(' starting listeners');

        // Run the server application
        $this->sockets = new Sockets($this->config->get('server.host'), $this->config->get('server.port'), $this->config->get('server.address'), $loop);
        $ran = $this->sockets->run();

        rec(' listeners closed');
        rec($ran);
    }

    /**
     * Event handler: tick
     */
    public function tick() {

        // Satisfy outstanding 'wants'
        $this->satisfy();

        foreach ($this->timing as $feature => &$last) {
            $frequency = val($feature, $this->frequencies);
            $delay = time() - $last;
            if ($delay < $frequency) {
                continue;
            }

            $last = time();
            $items = Event::fireReturn('data_query', $feature);

            foreach ($items as $data) {
                $this->want($feature, $data);
            }
        }

        // Check for motion
        $motion = $this->motion->sense();

        if (!$motion) {

            // Check for simulated motion


        }

        if ($motion === true) {
            Event::fire('motion');
        } else if ($motion === false) {
            Event::fire('still');
        }

    }

    /**
     * Register 'want'
     *
     * @param string $feature
     * @param array $data
     * @param boolean $fresh
     */
    public function want($feature, $data, $fresh = true) {
        $freshness = $fresh ? 'fresh only' : 'cached is ok';
        rec(" want '{$feature}', {$freshness}");
        switch ($feature) {
            case 'weather':
                $updateKey = "{$feature}:{$data['city']}";
                break;

            case 'time':
                $updateKey = "{$feature}:{$data['timezone']}";
                break;

            case 'news':
                $updateKey = "{$feature}";
                break;

            default:
                return;
                break;
        }

        // If fresh data is not required, allow a cache lookup
        if (!$fresh) {
            $cached = $this->getCache($updateKey);
            if ($cached) {
                rec("  satisfied instantly from cache");
                Event::fire('data_update', [$feature, $cached]);
                return true;
            }
        }

        $want = [
            'feature' => $feature,
            'key' => $updateKey,
            'data' => $data,
            'cache' => !$fresh
        ];
        $this->store->push('wants', $want);
        return true;
    }

    /**
     * Satisfy wanted data points
     *
     */
    public function satisfy() {

        // Don't multi-satisfy
        if ($this->store->get('satisfying', false)) {
            return;
        }

        $this->store->set('satisfying', true);

        $wants = $this->store->get('wants', []);
        $this->store->delete('wants');
        if (!count($wants)) {
            $this->store->delete('satisfying');
            return;
        }

        $satisfied = [];
        foreach ($wants as $want) {

            // If we've already updated this feature, skip it
            if (array_key_exists($want['key'], $satisfied)) {
                continue;
            }

            switch ($want['feature']) {
                case 'weather':
                    $result = $this->updateWeather($want['data']);
                    break;

                case 'time':
                    $result = $this->updateTime($want['data']);
                    break;

                case 'news':
                    $result = $this->updateNews($want['data']);
                    break;
            }

            if ($result) {
                if ($want['cache']) {
                    $this->setCache($want['feature'], $want['key'], $result);
                }
                Event::fire('data_update', [$want['feature'], $result]);
            }
        }

        $this->store->delete('satisfying');
    }

    /**
     * Cache fetched data
     *
     * @param string $feature
     * @param string $key
     * @param array $data
     */
    public function setCache($feature, $key, $data) {
        $ttl = val($feature, $this->frequencies, 60);
        \apcu_store($key, $data, $ttl);
    }

    /**
     * Fetch data from cache if available
     *
     * @param string $key
     */
    public function getCache($key) {
        $found = false;
        $cached = \apcu_fetch($key, $found);
        if ($found) {
            return $cached;
        }
        return false;
    }

    /**
     * Get mirror config
     *
     * @return array
     */
    public function mirrorConnected() {
        return $this->config->get('mirror');
    }

    /**
     * Get an API client
     */
    public function getAPI() {
        $api = new HttpClient();
        $api->setDefaultHeader('Content-Type', 'application/json');

        $userAgent = $this->config->get('interact.useragent');
        if ($userAgent) {
            $api->setDefaultHeader('User-Agent', $userAgent);
        }

        return $api;
    }

    /**
     * Poll the weather
     *
     * @param array $data
     */
    public function updateWeather($data) {
        $weatherConfig = $this->config->get('interact.weather');
        return Weather::get($data, $weatherConfig, $this->getAPI());
    }

    /**
     * Get news
     *
     * @param array $data
     */
    public function updateNews($data) {
        $newsConfig = $this->config->get('interact.news');
        return News::get($data, $newsConfig, $this->getAPI());
    }

    /**
     * Poll the time
     *
     * @param array $data
     */
    public function updateTime($data) {
        $timezone = $data['timezone'];
        $tz = new \DateTimeZone($timezone);
        $date = new \DateTime('now', $tz);

        return [
            'epoch' => $date->getTimestamp(),
            'full' => $date->format('Y-m-d H:i:s'),
            'date' => $date->format('Y-m-d'),
            'time' => $date->format('h:i'),
            'month' => $date->format('F j'),
            'day' => $date->format('l')
        ];
    }

    /**
     * Catch up when new client connects
     *
     */
    public function catchUp() {
        foreach ($this->timing as $feature => $last) {
            $items = Event::fireReturn('data_query', $feature);
            foreach ($items as $data) {
                $this->want($feature, $data, false);
            }
        }
    }

    /**
     * Reset timers
     */
    public function clearTimers() {
        rec("clearing data timers");
        foreach ($this->frequencies as $feature => $delay) {
            $this->timing[$feature] = 0;
        }
    }

}