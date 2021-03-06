<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\Systems\Mirror;

use Alice\Socket\SocketMessage;
use Alice\Server\UIClient;

use Ratchet\ConnectionInterface;

/**
 * ALICE UI Mirror instance
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
class MirrorClient extends UIClient {

    const MIRROR_AWAKE = '%s.mirror-awake';
    const MIRROR_ASLEEP = '%s.mirror-asleep';
    const MIRROR_HIBERNATE = '%s.mirror-hibernating';
    const MIRROR_DISPLAY_MODE = '%s.mirror-displaymode';
    const MIRROR_LOCKDIM = '%s.mirror-lockdim';

    const HIBERNATE_TIME = 120;

    /**
     * Mirror settings (from client)
     * @var array
     */
    protected $settings;

    /**
     * Mirror Client Constructor
     *
     * @param ConnectionInterface $connection
     */
    public function __construct(ConnectionInterface $connection) {
        parent::__construct($connection);

        $this->settings = [];
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
        $this->queueDataConnectors($connectors);

        // Sensors
        $sensors = val('sensors', $data);
        $this->queueSensorConnectors($sensors);

        // Let the mirror know that registration was successful
        $this->sendMessage('registered', [
            'version' => APP_VERSION
        ]);

        // Wake mirror on connect
        $this->motion();
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
     * Event hook: backend update
     *
     * When the backend has performed an update, it fires 'update' to offer the
     * data back to mirrors.
     *
     * @param string $sourceType
     * @param string $sourceFilter
     * @param mixed $data
     * @param boolean $wake
     */
    public function update($sourceType, $sourceFilter, $data, $wake = false) {

        // Handle 'still wanted' pings
        if ($data == 'ping') {
            return true;
        }

        switch ($sourceType) {
            default:
                $this->sendMessage('update', [
                    'source' => $sourceType,
                    'filter' => $sourceFilter,
                    'data' => $data
                ]);
                break;
        }

        // Allow data updates to wake the mirror
        if ($wake) {
            $this->motion();
        }
    }

    /**
     * Event hook: sensor event
     *
     * When a sensorsource sends an event, interested clients will receive a call
     * to 'sense'.
     *
     * @param string $sourceType
     * @param string $sourceID
     * @param mixed $data
     */
    public function sense($sourceType, $sourceID, $data) {

        // Handle 'still wanted' pings
        if ($data == 'ping') {
            return true;
        }

        switch ($sourceType) {
            case 'motion':

                // Read display mode from motion sensor
                $displayMode = valr('extra.display', $data, null);
                if (in_array($displayMode, ['on', 'off'])) {
                    apcu_store($this->getCacheKey(self::MIRROR_DISPLAY_MODE), $displayMode);
                }

                // Handle actual sensor data
                $sensorData = val('sensor', $data);
                if ($sensorData == 'still') {
                    $this->still();
                }
                if ($sensorData == 'motion') {
                    $this->motion();
                }
                break;
        }
    }

    /**
     * Hook motion event
     *
     * Alice has detected motion, so handle it here by waking the mirror and
     * locking in our guaranteed wake period with 'dimafter'.
     *
     */
    public function motion() {

        $dimAfter = val('dim', $this->settings);
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

        $tzName = valr('location.timezone', $this->settings);
        $tz = new \DateTimeZone($tzName);
        $date = new \DateTime('now', $tz);
        $time = $date->getTimestamp();

        // Set sleep time
        $willSleep = apcu_add($this->getCacheKey(self::MIRROR_ASLEEP), $time);

        // If we failed to set sleep time, mirror is already asleep
        if (!$willSleep && !$force) {
            apcu_delete($this->getCacheKey(self::MIRROR_AWAKE));

            // Maybe we need to hibernate too?
            $sleptAt = apcu_fetch($this->getCacheKey(self::MIRROR_ASLEEP));
            $sleeping = $time - $sleptAt;
            $hibernateTime = val('hibernate', $this->settings, self::HIBERNATE_TIME);
            if ($sleeping > $hibernateTime) {
                $this->hibernate();
            }

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

        $tzName = valr('location.timezone', $this->settings);
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

        $this->unhibernate();

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

        $tzName = valr('location.timezone', $this->settings);
        $tz = new \DateTimeZone($tzName);
        $date = new \DateTime('now', $tz);
        $time = $date->getTimestamp();

        // Only hibernate if we're known awake

        if (!$this->isAwake()) {
            return;
        }

        $this->rec("mirror going into hibernation");
        apcu_store($this->getCacheKey(self::MIRROR_HIBERNATE), $time);

        // Tell motion sensor to hibernate screen
        if ($this->findSensorWant('motion')) {
            $this->findSensorWant('motion')->getSource()->tellHibernate();
        }

        $this->sendMessage('hibernate');
        return true;
    }

    /**
     * Turn on actual screen
     *
     */
    public function unhibernate() {

        $tzName = valr('location.timezone', $this->settings);
        $tz = new \DateTimeZone($tzName);
        $date = new \DateTime('now', $tz);
        $time = $date->getTimestamp();

        // Only unhibernate if we're known asleep

        if ($this->isAwake()) {
            return false;
        }

        $this->rec("mirror waking from hibernation");

        $hibernatedAt = apcu_fetch($this->getCacheKey(self::MIRROR_HIBERNATE));
        if ($hibernatedAt) {
            $hibernating = $time - $hibernatedAt;
            $hibernateDate = new \DateTime('now', $tz);
            $date->setTimestamp($hibernatedAt);
            $hibernatedSince = $hibernateDate->format('Y-m-d H:i:s');
            $this->rec(" hibernating since {$hibernatedSince} ({$hibernating} seconds)");
            apcu_delete($this->getCacheKey(self::MIRROR_HIBERNATE));
        }

        // Tell motion sensor to unhibernate screen
        if ($this->findSensorWant('motion')) {
            $this->findSensorWant('motion')->getSource()->tellUnhibernate();
        }

        $this->sendMessage('unhibernate');
        return true;
    }

    /**
     * Check display mode (awake or not)
     *
     * @return boolean
     */
    public function isAwake() {
        $displayMode = apcu_fetch($this->getCacheKey(self::MIRROR_DISPLAY_MODE));
        if (!in_array($displayMode, ['on', 'off'])) {
            return false;
        }
        return $displayMode == 'on' ? true : false;
    }

}