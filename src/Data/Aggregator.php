<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\Data;

use Alice\Alice;

use Alice\Common\Store;
use Alice\Common\Event;

use Alice\Data\DataSource;
use Alice\Data\DataWant;

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
    const WANTED_PING = 300;

    /**
     * Data store
     * @var \Alice\Common\Store
     */
    protected $store;

    /**
     * List of DataSource objects
     * @var array<DataSource>
     */
    protected $sources;

    /**
     * List of DataWant objects
     * @var array<DataWant>
     */
    protected $wants;

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
        $this->sources = [];

        // Wants list
        $this->wants = [];

        // Last wanted ping
        $this->lastPing = time();

        Alice::loop()->addPeriodicTimer(10, [$this, 'tick']);
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
     * Create configured DataSource instance
     *
     * @param string $type
     * @param array $config
     * @return DataSource
     */
    public static function loadSource($type, $config) {
        return DataSource::load($type, $config);
    }

    /**
     * Add aggregated DataSource
     *
     * @param DataSource $source
     * @return DataSource
     */
    public function addSource(DataSource $source) {
        $sourceID = $source->getID();

        if (!$this->haveSource($source)) {
            $this->sources[$sourceID] = $source;
        }

        return $this->sources[$sourceID];
    }

    /**
     * Remove aggregated DataSource
     *
     * @param DataSource $source
     */
    public function removeSource(DataSource $source) {
        if (!$this->haveSource($source)) {
            return false;
        }

        $sourceID = $source->getID();
        unset($this->sources[$sourceID]);
        return true;
    }

    /**
     * Test if DataSource is already tracked
     *
     * @param DataSource $source
     * @return boolean
     */
    public function haveSource(DataSource $source) {
        $sourceID = $source->getID();
        if (array_key_exists($sourceID, $this->sources)) {
            return true;
        }
        return false;
    }

    /**
     * Create configured DataWant instance
     *
     * @param string $type
     * @param string $filter
     * @return DataWant
     */
    public function loadWant($type, $filter) {
        $want = DataWant::load($type, $filter);
        $resolved = $this->resolveSource($want);
        if (!$resolved) {
            return false;
        }
        return $want;
    }

    /**
     * Add tracked DataWant
     *
     * @param DataWant $want
     * @return DataWant|boolean:false
     */
    public function addWant(DataWant $want) {
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
     * Remove tracked DataWant
     *
     * @param DataWant $want
     * @param boolean $destroy optional. destroy wants. default true.
     */
    public function removeWant(DataWant $want, $destroy = true) {
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
     * @param DataWant $want
     * @return boolean
     */
    public function haveWant(DataWant $want) {
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
     * Resolve DataSource for DataWant
     *
     * @param DataWant $want
     * @return boolean
     */
    protected function resolveSource(DataWant $want) {
        foreach ($this->sources as $source) {
            if (!$source->getType() == $want->getType()) {
                continue;
            }

            if ($source->canSatisfy($want->getFilter())) {
                $want->setSource($source);
                return true;
            }
        }
        return false;
    }

    /**
     * Event handler: tick
     */
    public function tick() {

        // Collect 'ready' wants
        $ready = $this->collectReady();

        // Pull fresh data from want sources
        foreach ($ready as $want) {
            $want->updateCycle();
        }

        // Occasionally check if wants should be purged
        if ((time() - $this->lastPing) > self::WANTED_PING) {
            $this->rec("testing still wanted");
            $this->lastPing = time();
            foreach ($this->wants as $want) {
                $stillWanted = $want->getStillWanted();
                $stillWantedStr = $stillWanted ? 'keep' : 'discard';
                $this->rec(sprintf(" %s -> %s", $want->getID(), $stillWantedStr));
                if (!$stillWanted) {
                    $this->removeWant($want);
                }
            }
        }
    }

    /**
     * Collect DataWants that are ready to update
     *
     * @return array<DataWant>
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