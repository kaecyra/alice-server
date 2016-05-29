<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\Data;

use Alice\Common\Event;

use Alice\Data\DataSource;

use Exception;

/**
 * ALICE DataWant
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
class DataWant {

    /**
     * DataWant type
     * @var string
     */
    protected $type;

    /**
     * DataWant filter
     * @var string
     */
    protected $filter;

    /**
     * DataWant config
     * @var array
     */
    protected $config;

    /**
     * DataWant ID
     * @var string
     */
    protected $id;

    /**
     * DataSource
     * @var DataSource
     */
    protected $source;

    /**
     * Active want?
     * @var boolean
     */
    protected $active;

    /**
     * Last time updated
     * @var integer
     */
    protected $lastUpdated;

    /**
     * DataSource constructor
     *
     * No direct DataWant instantiation is permitted, must be load()ed.
     *
     * @param string $type
     * @param string $filter
     */
    protected function __construct($type, $filter) {
        $this->type = $type;
        $this->filter = $filter;
        $this->id = null;
        $this->config = null;
        $this->active = false;
        $this->lastUpdated = 0;
    }

    /**
     * Create configured DataWant instance
     *
     * @param string $type
     * @param string $filter
     * @return \Alice\Data\DataWant
     * @throws Exception
     */
    public static function load($type, $filter) {
        return new DataWant($type, $filter);
    }

    /**
     * Check if configured
     *
     * @return boolean
     */
    public function isReady() {
        if ($this->source instanceof DataSource && is_array($this->config)) {
            return true;
        }
        return false;
    }

    /**
     * Set config
     *
     * @param array $config
     */
    public function setConfig($config) {
        $this->config = $config;
    }

    /**
     * Get config
     *
     * @return array
     */
    public function getConfig() {
        return $this->config;
    }

    /**
     * Get DataWant type
     *
     * @return string
     */
    public function getType() {
        return $this->type;
    }

    /**
     * Get DataWant filter
     *
     * @return string
     */
    public function getFilter() {
        return $this->filter;
    }

    /**
     * Get DataWant ID
     *
     * @return string
     */
    public function getID() {
        if (is_null($this->id)) {
            if ($this->isReady()) {
                $this->id = $this->source->buildWantID($this->filter, $this->config);
            } else {
                return "{$this->type}/{$this->filter}-pending";
            }
        }
        return $this->id;
    }

    /**
     * Set DataSource
     *
     * @param DataSource $source
     */
    public function setSource(DataSource $source) {
        $this->source = $source;
    }

    /**
     * Get DataSource
     *
     * @return DataSource
     */
    public function getSource() {
        return $this->source;
    }

    /**
     * Get required configuration items from source
     *
     * @return array
     */
    public function getRequiredFields() {
        return $this->source->getRequiredFields($this->filter);
    }

    /**
     * Set active status
     *
     */
    public function setActive() {
        $this->active = true;
    }

    /**
     * Get last time updated
     *
     * @return DateTime
     */
    public function getLastUpdated() {
        return $this->lastUpdated;
    }

    /**
     * Should an update be performed?
     *
     * @return boolean
     */
    public function getShouldUpdate() {
        return (time() - $this->lastUpdated) > $this->source->getFrequency();
    }

    /**
     * Get data event name
     *
     * When this Want receives new data, it will fire an event to propagate the
     * data to consumers. This method gets the name of the event that will be
     * fired.
     *
     * @return string
     */
    public function getEventID() {
        return "dataevent-".$this->getID();
    }

    /**
     * Run pull/push update cycle
     *
     * @param boolean $fresh optional. require fresh responses. default true.
     * @return boolean data pulled ok
     */
    public function updateCycle($fresh = true) {
        $data = $this->pullUpdate($fresh);
        if ($data) {
            $this->pushUpdate($data);
            return true;
        }
        return false;
    }

    /**
     * Pull update from DataSource
     *
     * @param boolean $fresh optional. require fresh responses. default true.
     * @return array|boolean:false
     */
    public function pullUpdate($fresh = true) {

        // Cached data OK?
        if (!$fresh) {
            $cached = $this->getCache('data');
            if ($cached) {
                return $cached;
            }
        }

        // Get fresh data
        $this->rec('pulling update');
        $this->lastUpdated = time();
        $data = $this->source->fetch($this->filter, $this->config);
        if ($data) {
            $this->setCache($data, 'data');
            return $data;
        }
        return false;
    }

    /**
     * Push update to clients
     *
     * @param array $update
     */
    public function pushUpdate($update) {
        Event::fire($this->getEventID(), [$this->type, $this->filter, $update]);
    }

    /**
     * Cache fetched data
     *
     * @param array $data
     * @param string $key optional.
     */
    protected function setCache($data, $key = null) {
        $ttl = $this->source->getFrequency();
        if (!$key) {
            $key = "data";
        }
        $fullKey = "cache-".$this->getID()."-{$key}";
        \apcu_store($fullKey, $data, $ttl);
    }

    /**
     * Fetch data from cache if available
     *
     * @param string $key optional.
     * @return mixed|boolean
     */
    protected function getCache($key = null) {
        if (!$key) {
            $key = "data";
        }
        $fullKey = "cache-".$this->getID()."-{$key}";

        $found = false;
        $cached = \apcu_fetch($fullKey, $found);
        if ($found) {
            return $cached;
        }
        return false;
    }

    /**
     * Test if this want is still needed
     *
     * Fires a fireFilter to all listeners to see if anyone answers.
     *
     * @return boolean
     */
    public function getStillWanted() {
        $wanted = Event::fireReturn($this->getEventID(), $this->type, $this->filter, 'ping');
        if ($wanted || count($wanted)) {
            return true;
        }
        return false;
    }

    /**
     * Shut down want
     *
     */
    public function __destruct() {
        if ($this->active) {
            $this->rec("shutting down");
        }
    }

    /**
     * Record want specific mesage
     *
     * @param mixed $message
     * @param integer $level
     * @param integer $options
     */
    public function rec($message, $level = \Alice\Daemon\Daemon::LOG_L_APP, $options = \Alice\Daemon\Daemon::LOG_O_SHOWTIME) {
        if (is_array($message) || is_object($message)) {
            $message = print_r($message, true);
        }
        rec(sprintf("[datawant: %s] %s", $this->getID(), $message), $level, $options);
    }

}