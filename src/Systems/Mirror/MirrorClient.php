<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\Systems\Mirror;

use Alice\Alice;

use Alice\Common\Event;

use Alice\Socket\SocketMessage;
use Alice\Server\SocketClient;

use Ratchet\ConnectionInterface;

/**
 * ALICE UI Mirror instance
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
class MirrorClient extends SocketClient {

    const MIRROR_AWAKE = '%s.mirror-awake';
    const MIRROR_ASLEEP = '%s.mirror-asleep';
    const MIRROR_LOCKDIM = '%s.mirror-lockdim';

    /**
     * Mirror settings (from client)
     * @var array
     */
    protected $settings;

    /**
     * Mirror connectors
     * @var array<DataWant>
     */
    protected $connectors;

    public function __construct(ConnectionInterface $connection) {
        parent::__construct($connection);

        $this->settings = [];
        $this->connectors = [];
    }

    /**
     * Handle mirror registrations
     *
     * @param SocketMessage $message
     */
    public function message_register(SocketMessage $message) {
        $data = $message->getData();
        return $this->registerClient($data);
    }

    /**
     * Register mirror client
     *
     * @param array $data
     * @return boolean
     */
    protected function registerClient($data) {
        $this->rec(" mirror client registering");
        sleep(1);

        // Name
        $name = val('name', $data);
        $id = val('id', $data);
        if (!$name || !$id) {
            return $this->sendError("cannot register: name and id are both required");
        }
        $this->registerClientID($name, $id);
        $this->rec("  name: {$name}");

        // Settings
        $settings = val('settings', $data);
        if (!$settings) {
            return $this->sendError("cannot register: settings are required");
        }
        $this->settings = $settings;

        // Register sensor hooks
        $this->hook('motion', [$this, 'motion']);
        $this->hook('still', [$this, 'still']);

        // Data Connectors
        $connectors = val('sources', $data);
        $required = ['type', 'filter'];
        if (is_array($connectors) && count($connectors)) {
            foreach ($connectors as $connector) {
                foreach ($required as $requiredField) {
                    if (!array_key_exists($requiredField, $connector)) {
                        $this->rec($connector);
                        $this->sendError("  ignoring malforumed connector ({$connector['type']}), no '{$requiredField}'");
                        continue;
                    }
                }
                $registered = $this->registerConnector($connector);
                if ($registered !== true) {
                    $this->sendError("  connector ({$connector['type']}) failed to register: {$registered}");
                }
            }
        }

        // Let the mirror know that registration was successful
        $this->sendMessage('registered');
    }

    /**
     * Handle mirror catchup
     *
     * @param SocketMessage $message
     */
    public function message_catchup(SocketMessage $message) {
        $this->rec("mirror asked to be caught up");
        foreach ($this->connectors as $want) {
            $want->updateCycle(false);
        }
    }

    /**
     * TEST: send mirror to sleep
     *
     * @param SocketMessage $message
     */
    public function message_sleepme(SocketMessage $message) {
        $this->rec(" mirror asked to go to sleep");
        sleep(1);
        $this->sleep();
    }

    /**
     * TEST: send mirror to sleep
     *
     * @param SocketMessage $message
     */
    public function message_wakeme(SocketMessage $message) {
        $this->rec(" mirror asked to be woken up");
        sleep(1);
        $this->wake();
    }

    /**
     * Register a data connector on this mirror
     *
     * @param array $connector
     * @return string|boolean:true string error or boolean true success
     */
    protected function registerConnector($connector) {
        $sourceType = $connector['type'];
        $sourceFilter = $connector['filter'];

        // Get DataWant instance
        $want = Alice::go()->aggregator()->loadWant($sourceType, $sourceFilter);
        if (!$want) {
            return "could not resolve source";
        }

        $this->rec("registering connector: ".$want->getID());

        $requiredFields = $want->getRequiredFields();

        $fieldAliases = [
            'city' => 'location.city',
            'units' => 'location.units',
            'latitude' => 'location.latitude',
            'longitude' => 'location.longitude'
        ];

        $connectorConfig = val('config', $connector, []);
        if (!is_array($connectorConfig)) {
            $connectorConfig = [];
        }

        // Allow intelligent re-use of client config settings for data connectors
        foreach ($requiredFields as $requiredField) {
            // If this setting isn't defined in the connector config
            if (!array_key_exists($requiredField, $connectorConfig)) {
                // And we know how to find it in the client config
                if (array_key_exists($requiredField, $fieldAliases)) {
                    // Load it from there
                    $connectorConfig[$requiredField] = valr($fieldAliases[$requiredField], $this->settings);
                }
            }

            if (!array_key_exists($requiredField, $connectorConfig)) {
                return "missing config '{$requiredField}'";
            }
        }

        // Push config to DataWant and aggregate
        $want->setConfig($connectorConfig);
        $want = Alice::go()->aggregator()->addWant($want);
        if (!$want) {
            return "could not add connector to aggregator";
        }

        // Register data collection event hook
        $this->hook($want->getEventID(), [$this, "update"]);

        // Remember want
        $wantID = $want->getID();
        $this->connectors[$wantID] = $want;
        return true;
    }

    /**
     * Event hook: backend update
     *
     * When the backend has performed an update, it fires 'update' to offer the
     * data back to mirrors.
     *
     * @param string $sourceType
     * @param string $sourceFilter
     * @param mixed $data
     */
    public function update($sourceType, $sourceFilter, $data) {

        // Handle 'still wanted' pings
        if ($data == 'ping') {
            return true;
        }

        switch ($sourceType) {
            case 'weather':
            case 'news':
                $this->sendMessage('update', [
                    'source' => $sourceType,
                    'filter' => $sourceFilter,
                    'data' => $data
                ]);
                break;

            default:
                break;
        }
    }

    /**
     * Get namespaced cache key
     *
     * @param string $key
     * @return string
     */
    protected function getCacheKey($key) {
        return sprintf($key, $this->name);
    }

    /**
     * Put mirror to sleep
     *
     * @param boolean $force force mirror to sleep even if already asleep
     * @return boolean slept or not
     */
    public function sleep($force = false) {

        $tzName = val('timezone', $this->settings);
        $tz = new \DateTimeZone($tzName);
        $date = new \DateTime('now', $tz);
        $time = $date->getTimestamp();

        // Set sleep time
        $willSleep = apcu_add($this->getCacheKey(self::MIRROR_ASLEEP), $time);

        // If we failed to set sleep time, mirror is already asleep
        if (!$willSleep && !$force) {
            apcu_delete($this->getCacheKey(self::MIRROR_AWAKE));
            return false;
        }

        $this->rec("mirror going to sleep");

        // Report on and erase wake time
        $wokeAt = apcu_fetch($this->getCacheKey(self::MIRROR_AWAKE));
        if ($wokeAt !== false) {
            $waking = $time - $wokeAt;
            $wokeDate = new \DateTime('now', $tz);
            $date->setTimestamp($wokeAt);
            $wokeSince = $wokeDate->format('Y-m-d H:i:s');
            $this->rec(" awake since {$wokeSince} ({$waking} seconds)");
            apcu_delete($this->getCacheKey(self::MIRROR_AWAKE));
        }

        $this->sendMessage('sleep');
        return true;
    }

    /**
     * Wake mirror up
     *
     * @param boolean $force force mirror to wake even if already awake
     * @return boolean woke or not
     */
    public function wake($force = false) {

        $tzName = val('timezone', $this->settings);
        $tz = new \DateTimeZone($tzName);
        $date = new \DateTime('now', $tz);
        $time = $date->getTimestamp();

        // Set wake time
        $willWake = apcu_add($this->getCacheKey(self::MIRROR_AWAKE), $time);

        // If we failed to set wake time, mirror is already awake
        if (!$willWake && !$force) {
            apcu_delete($this->getCacheKey(self::MIRROR_ASLEEP));
            return false;
        }

        $this->rec("mirror waking up");

        // Report on and erase sleep time
        $sleptAt = apcu_fetch($this->getCacheKey(self::MIRROR_ASLEEP));
        if ($sleptAt !== false) {
            $sleeping = $time - $sleptAt;
            $sleptDate = new \DateTime('now', $tz);
            $date->setTimestamp($sleptAt);
            $sleptSince = $sleptDate->format('Y-m-d H:i:s');
            $this->rec(" slept since {$sleptSince} ({$sleeping} seconds)");
            apcu_delete($this->getCacheKey(self::MIRROR_ASLEEP));
        }

        $this->sendMessage('wake');
        return true;
    }

    /**
     * Turn off actual screen
     *
     */
    public function hibernate() {
        $this->sleep();
    }

    /**
     * Turn on actual screen
     *
     */
    public function unhibernate() {
        $this->wake();
    }

    /**
     * Hook motion event
     *
     * Alice has detected motion, so handle it here by waking the mirror and
     * locking in our guaranteed wake period with 'dimafter'.
     *
     */
    public function motion() {

        $dimAfter = val('dimafter', $this->settings);
        $dimAt = time() + $dimAfter;
        apcu_store($this->getCacheKey(self::MIRROR_LOCKDIM), $dimAt, $dimAfter);

        // Got motion, wake the mirror
        $this->wake();

    }

    /**
     * Hook still event
     *
     * Alice has detected no motion, so the mirror must decide if that means we
     * need to dim the display. This is based on whether there is a still a
     * mutex keeping the display on, or not.
     *
     */
    public function still() {

        $dimLock = apcu_fetch($this->getCacheKey(self::MIRROR_LOCKDIM));
        if ($dimLock) {
            if ($dimLock > time()) {
                $lockedFor = $dimLock - time();
                $this->rec("still lockout, {$lockedFor}s left");
                return;
            }

            // Lock failed to remove via TTL, remove manually
            apcu_delete($this->getCacheKey(self::MIRROR_LOCKDIM));
        }

        // No motion for $dimAfter seconds, sleep
        $this->sleep();

    }

}