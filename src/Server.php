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

use React\EventLoop\Factory as LoopFactory;

use Garden\Http\HttpClient;

use Exception;

/**
 * ALICE Websocket Daemon
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
class Server implements App {

    protected $store;
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
        'weather' => 600
    ];

    /**
     * List of timing arrays
     * @var array
     */
    protected $timing = [
        'weather' => 0
    ];

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

        // Hooks

        // Predefine timers as now
        foreach ($this->timing as $timer => &$last) {
            $last = 0;
        }
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
        $loop->addPeriodicTimer(30, [$this, 'tick']);

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
        foreach ($this->timing as $feature => &$last) {
            if ((time() - $last) < val($feature, $this->frequencies)) {
                continue;
            }
            $last = time();
            $items = Event::fireReturn('data_query', $feature);

            rec("update '{$feature}'");
            foreach ($items as $data) {
                switch ($feature) {
                    case 'weather':
                        $this->updateWeather($data);
                        break;
                }
            }
        }
    }

    /**
     * Poll the weather
     *
     * @param array $data
     */
    public function updateWeather($data) {
        $city = val('city', $data);
        $cityCode = val('citycode', $data);
        $units = val('units', $data);
        rec(" requesting updated weather data: {$city}");

        $host = $this->config->get('interact.weather.host');
        $path = $this->config->get('interact.weather.path');
        $key = $this->config->get('interact.weather.key');

        $api = new HttpClient($host);
        $api->setDefaultHeader('Content-Type', 'application/json');;

        $response = $api->get($path, [
            'id' => $cityCode,
            'units' => $units,
            'APPID' => $key
        ]);

        if ($response->isResponseClass('2xx')) {
            $weatherData = $response->getBody();

            $weather = val('weather', $weatherData);
            $temp = valr('main.temp', $weatherData);

            $summary = [];
            foreach ($weather as $system) {
                $summary[] = $system['description'];
            }
            $summary = implode(', ', $summary);
            rec("  {$temp} degrees C with {$summary}");

            Event::fire('data_update', ['weather', $weatherData]);
        }
    }

}