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

        // Hooks

        // Predefine timers as now
        $this->clearTimers();

        Event::hook('catchup', [$this, 'catchUp']);
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
        \apc_store($key, $data, $ttl);
    }

    /**
     * Fetch data from cache if available
     *
     * @param string $key
     */
    public function getCache($key) {
        $found = false;
        $cached = \apc_fetch($key, $found);
        if ($found) {
            return $cached;
        }
        return false;
    }

    /**
     * Poll the weather
     *
     * @param array $data
     */
    public function updateWeather($data) {
        $city = val('city', $data);
        $latitude = val('latitude', $data);
        $longitude = val('longitude', $data);
        $units = val('units', $data);
        rec(" requesting updated weather data: {$city}");

        $host = $this->config->get('interact.weather.host');
        $path = $this->config->get('interact.weather.path');
        $key = $this->config->get('interact.weather.key');

        $path = formatString($path, [
            'api' => $key,
            'latitude' => $latitude,
            'longitude' => $longitude
        ]);

        $api = new HttpClient($host);
        $api->setDefaultHeader('Content-Type', 'application/json');

        $response = $api->get($path, [
            'units' => $units,
            'exclude' => 'daily'
        ]);

        if ($response->isResponseClass('2xx')) {
            $weatherData = $response->getBody();

            $current = val('currently', $weatherData);
            $minute = val('minutely', $weatherData);
            $hourly = val('hourly', $weatherData);

            $summary = val('summary', $hourly);
            $minSummary = val('summary', $minute);

            $weather = array_merge($current, [
                'now' => rtrim($minSummary, '.'),
                'today' => rtrim($summary, '.'),
                'summary' => rtrim($current['summary'], '.')
            ]);

            $round = [
                'temperature' => 0,
                'apparentTemperature' => 0,
                'dewPoint' => 0,
                'visibility' => 1
            ];
            foreach ($round as $roundKey => $roundPrecision) {
                $weather[$roundKey] = round($weather[$roundKey], $roundPrecision);
            }

            $percent = [
                'humidity',
                'cloudCover',
                'precipProbability'
            ];
            foreach ($percent as $percentKey) {
                $weather[$percentKey] = round($weather[$percentKey] * 100, 0);
            }

            $temp = val('temperature', $weather);
            rec("  {$temp} degrees C with {$minSummary}");

            return $weather;
        }
        return false;
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
     * Get news
     * @param type $data
     */
    public function updateNews($data) {

        rec(" requesting updated news data");

        $host = $this->config->get('interact.news.host');
        $path = $this->config->get('interact.news.path');
        $key = $this->config->get('interact.news.key');
        $useragent = $this->config->get('interact.news.useragent');

        $api = new HttpClient($host);
        $api->setDefaultHeader('Content-Type', 'application/json');
        $api->setDefaultHeader('User-Agent', $useragent);

        $arguments = $this->config->get('interact.news.arguments');

        $response = $api->get($path, array_merge($arguments, [
            'api-key' => $key
        ]));

        if ($response->isResponseClass('2xx')) {
            $newsData = $response->getBody();
            $results = val('results', $newsData);

            $news = [];
            $maxResults = val('limit', $data, 6);
            foreach ($results as $result) {
                if ($result['item_type'] != 'Article') {
                    continue;
                }
                if (count($news) >= $maxResults) {
                    break;
                }

                $news[] = [
                    'title' => $result['title'],
                    'url' => $result['url'],
                    'source' => $result['source'],
                    'id' => sha1($result['url'])
                ];
            }

            return [
                'count' => count($news),
                'articles' => $news
            ];
        }
        return false;
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