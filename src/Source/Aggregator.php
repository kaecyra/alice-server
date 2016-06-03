<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\Source;

use Alice\Alice;

use Alice\Common\Store;
use Alice\Common\Event;

use Alice\Source\Source;
use Alice\Source\Want;

use Garden\Http\HttpClient;

/**
 * ALICE Data Aggregator
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
class Aggregator {

    /**
     * How long to delay between 'still wanted' pings
     */
    const WANTED_CYCLE = 300;

    /**
     * How long to delay between resolution cycles
     */
    const PENDING_CYCLE = 1;

    const KEY_STILLWANTED = 'stillwanted';
    const KEY_PENDING = 'pending';

    /**
     * Data store
     * @var \Alice\Common\Store
     */
    protected $store;

    /**
     * List of DataSource objects
     * @var array<DataSource>
     */
    protected $datasources;

    /**
     * List of SensorSource objects
     * @var array<SensorSource>
     */
    protected $sensorsources;

    /**
     * List of Want objects
     * @var array<Want>
     */
    protected $wants;

    /**
     * Pending wants
     * @var array<Want>
     */
    protected $pending;

    /**
     * Aggregator client config
     * @var array
     */
    protected $config;

    /**
     * Last wanted ping
     * @var integer
     */
    protected $lastPing;

    /**
     * Constructor
     *
     */
    public function __construct() {

        // Store
        $this->store = new Store;

        // Sources list
        $this->datasources = [];
        $this->sensorsources = [];

        // Wants list
        $this->wants = [];

        // Last timers
        $this->store->set(self::KEY_STILLWANTED, time());
        $this->store->set(self::KEY_PENDING, time());

        Alice::loop()->addPeriodicTimer(1, [$this, 'tick']);
    }

    /**
     * Client configuration
     *
     * @param array $config
     */
    public function setClientConfiguration($config) {
        $this->config = $config;
    }

    /**
     * Get an API client
     *
     * @return Garden\Http\HttpClient
     */
    public function getClient() {
        $client = new HttpClient();
        $client->setDefaultHeader('Content-Type', 'application/json');

        $userAgent = val('useragent', $this->config, 'kaecyra/alice-server');
        if ($userAgent) {
            $userAgent .= '/'.APP_VERSION;
            $client->setDefaultHeader('User-Agent', $userAgent);
        }

        return $client;
    }

    /**
     * Create configured Source instance
     *
     * @param string $class
     * @param string $type
     * @param array $config
     * @return Source
     */
    public static function loadSource($class, $type, $config) {
        switch ($class) {
            case Source::CLASS_DATA:
                return DataSource::load($type, $config);
                break;

            case Source::CLASS_SENSOR:
                return SensorSource::load($type, $config);
                break;

            default:
                $this->rec("Unknown source category: {$class}");
                return false;
                break;
        }
    }

    /**
     * Add aggregated Source
     *
     * @param Source $source
     * @return Source
     */
    public function addSource(Source $source) {
        $sourceID = $source->getID();
        switch ($source->getClass()) {
            case Source::CLASS_DATA:
                if (!$this->haveSource($source)) {
                    $this->datasources[$sourceID] = $source;
                }
                return $this->datasources[$sourceID];
                break;

            case Source::CLASS_SENSOR:
                if (!$this->haveSource($source)) {
                    $this->sensorsources[$sourceID] = $source;
                }
                return $this->sensorsources[$sourceID];
                break;
        }
    }

    /**
     * Remove aggregated Source
     *
     * @param Source $source
     */
    public function removeSource(Source $source) {
        if (!$this->haveSource($source)) {
            return false;
        }

        $sourceID = $source->getID();
        switch ($source->getClass()) {
            case Source::CLASS_DATA:
                unset($this->datasources[$sourceID]);
                break;

            case Source::CLASS_SENSOR:
                unset($this->sensorsources[$sourceID]);
                break;
        }

        return true;
    }

    /**
     * Test if Source is already tracked
     *
     * @param Source $source
     * @return boolean
     */
    public function haveSource(Source $source) {
        $sourceID = $source->getID();
        if (array_key_exists($sourceID, $this->datasources)) {
            return true;
        }
        if (array_key_exists($sourceID, $this->sensorsources)) {
            return true;
        }
        return false;
    }

    /**
     * Create configured Want instance
     *
     * @param string $type
     * @param string $filter
     * @return Want
     */
    public function loadWant($class, $type, $filter) {
        $want = new Want($class, $type, $filter);
        return $want;
    }

    /**
     * Queue want for resolution
     *
     * @param Want $want
     * @param callback $callback
     */
    public function queueWant(Want $want, callable $callback) {
        $this->pending[$want->getUID()] = [
            'want' => $want,
            'callback' => $callback
        ];
    }

    /**
     * Add tracked Want
     *
     * @param Want $want
     * @return Want|boolean:false
     */
    public function addWant(Want $want) {
        if (!$want->isReady()) {
            return false;
        }

        $wantID = $want->getID();

        // Already have want, so this is a soft success
        if (!$this->haveWant($want)) {
            $this->wants[$wantID] = $want;
            $want->setActive();
        }

        return $this->wants[$wantID];
    }

    /**
     * Remove tracked Want
     *
     * @param Want $want
     * @param boolean $destroy optional. destroy wants. default true.
     */
    public function removeWant(Want $want, $destroy = true) {
        if (!$want->isReady()) {
            return false;
        }

        // Don't have this want, so this is a soft success
        if (!$this->haveWant($want)) {
            return true;
        }

        $wantID = $want->getID();
        if ($destroy) {
            unset($want);
        }
        unset($this->wants[$wantID]);
        return true;
    }

    /**
     * Test if want is already tracked
     *
     * @param Want $want
     * @return boolean
     */
    public function haveWant(Want $want) {
        if (!$want->isReady()) {
            return false;
        }

        $wantID = $want->getID();
        if (array_key_exists($wantID, $this->wants)) {
            return true;
        }
        return false;
    }

    /**
     * Resolve Source for Want
     *
     * @param Want $want
     * @return boolean
     */
    protected function resolveSource(Want $want) {
        $this->rec("resolve source for ".$want->getUID()." (".$want->getClass().":".$want->getType()."/".$want->getFilter().")");
        switch ($want->getClass()) {
            case Source::CLASS_DATA:
                foreach ($this->datasources as $source) {
                    if ($source->getType() != $want->getType()) {
                        continue;
                    }
                    if ($source->canSatisfy($want->getFilter())) {
                        $want->setSource($source);
                        return true;
                    }
                }
                break;

            case Source::CLASS_SENSOR:
                foreach ($this->sensorsources as $source) {
                    if ($source->getType() != $want->getType()) {
                        continue;
                    }
                    if ($source->getID() == $want->getFilter()) {
                        $want->setSource($source);
                        return true;
                    }
                }
                break;
        }
        return false;
    }

    /**
     * Event handler: tick
     */
    public function tick() {

        // Resolve pending wants
        if (count($this->pending) && ((time() - $this->store->get(self::KEY_PENDING)) > self::PENDING_CYCLE)) {
            $this->cyclePending();
        }

        // Collect 'ready' wants
        $ready = $this->collectReady();

        // Pull fresh data from want sources
        foreach ($ready as $want) {
            $want->updateCycle();
        }

        // Occasionally check if wants should be purged
        if ((time() - $this->store->get(self::KEY_STILLWANTED)) > self::WANTED_CYCLE) {
            $this->cycleStillWanted();
        }
    }

    /**
     * Collect Wants that are ready to update
     *
     * @return array<Want>
     */
    public function collectReady() {
        $ready = [];
        foreach ($this->wants as $want) {
            if ($want->getShouldUpdate()) {
                $ready[] = $want;
            }
        }
        return $ready;
    }

    /**
     * Cycle pending Wants
     *
     * Check if pending wants can now be resolved due to newly connected sources.
     */
    public function cyclePending() {
        $this->store->set(self::KEY_PENDING, time());
        $pendingKeys = array_keys($this->pending);

        foreach ($pendingKeys as $pendingID) {
            $pending = $this->pending[$pendingID];
            $pendingWant = $pending['want'];
            $resolved = $this->resolveSource($pendingWant);
            if ($resolved) {
                $pendingCallback = $pending['callback'];
                $this->rec(sprintf(" resolved '%s' to '%s'", $pendingID, $pendingWant->getSource()->getID()));
                $added = $pendingCallback($pendingWant);
                if ($added === true) {
                    unset($this->pending[$pendingID]);
                } else {
                    $this->rec(" failed to prepare: {$added}");
                }
            }
        }
    }

    /**
     * Cycle active Wants
     *
     * Check if wants should be discarded due to lack of interest from subscribed
     * clients.
     */
    public function cycleStillWanted() {
        $this->rec("testing still wanted");
        $this->store->set(self::KEY_STILLWANTED, time());
        $wantKeys = array_keys($this->wants);

        foreach ($wantKeys as $wantID) {
            $want = $this->wants[$wantID];
            $stillWanted = $want->getStillWanted();
            $stillWantedStr = $stillWanted ? 'keep' : 'discard';
            $this->rec(sprintf(" %s -> %s", $want->getID(), $stillWantedStr));
            if (!$stillWanted) {
                $this->removeWant($want);
            }
        }
    }

    /**
     * Record aggregator specific message
     *
     * @param mixed $message
     * @param integer $level
     * @param integer $options
     */
    public function rec($message, $level = \Alice\Daemon\Daemon::LOG_L_APP, $options = \Alice\Daemon\Daemon::LOG_O_SHOWTIME) {
        if (is_array($message) || is_object($message)) {
            $message = print_r($message, true);
        }
        rec("[aggregator] ".$message, $level, $options);
    }

}