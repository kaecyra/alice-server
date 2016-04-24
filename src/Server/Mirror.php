<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\Server;

use Alice\Server\Message;
use Alice\Common\Event;

/**
 * ALICE UI Mirror instance
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
class Mirror {

    /**
     * Ratchet connection
     * @var \Ratchet\ConnectionInterface
     */
    protected $connection;

    /**
     * Mirror name
     * @var string
     */
    protected $name;

    /**
     * Mirror features
     * @var array
     */
    protected $features;

    /**
     * Mirror config
     * @var array
     */
    protected $config;




    public function __construct(\Ratchet\ConnectionInterface $connection) {
        $this->connection = $connection;

        rec(" mirror startup");
        $this->hook('data_query', [$this, 'query']);
        $this->hook('data_update', [$this, 'update']);

        $this->hook('motion', [$this, 'motion']);

        $connected = Event::fireReturn('connected');
        $this->config = array_pop($connected);
   }

    /**
     * Register a hook
     *
     * @param string $event
     * @param type $callback
     */
    public function hook($event, $callback) {
        $signature = Event::hook($event, $callback);
        $this->registerHook($event, $signature);
    }

    /**
     * Register a module hook
     *
     * @param string $event
     * @param string $signature
     */
    public function registerHook($event, $signature) {
        rec("  registered hook for '{$event}' -> {$signature}");
        $this->hooks[$event] = $signature;
    }

    /**
     * Get list of events hooked by this module
     *
     * @return array
     */
    public function getHooks() {
        return $this->hooks;
    }

    /**
     * Send a message to the mirror
     *
     * @param string $method
     * @param mixed $data
     */
    public function send($method, $data = null) {
        $message = Message::compile($method, $data);
        $this->connection->send($message);
    }

    /**
     * Send error (optional) and disconnect
     *
     * @param string $reason optional.
     */
    public function error($reason = null) {
        if ($reason) {
            rec("client error: {$reason}");
            $this->send('error', [
                'reason' => $reason
            ]);
        } else {
            rec("client error");
        }
        $this->connection->close();
    }

    /**
     * Handle incoming message from mirror UI
     *
     * @param Message $message
     */
    public function sent(Message $message) {
        rec("ui client message: ".$message->getMethod());

        // Route to message handler
        $call = 'message_'.$message->getMethod();
        if (is_callable([$this, $call])) {
            $this->$call($message);
        } else {
            rec(sprintf(" could not handle message: unknown type '{%s}'", $message->getMethod()));

        }
    }

    /**
     * Handle mirror registrations
     *
     * @param Message $message
     */
    public function message_register(Message $message) {
        rec(" client mirror registering");
        $data = $message->getData();

        sleep(1);

        // Name
        $name = val('name', $data);
        if (!$name) {
            return $this->error("cannot register: name is required");
        }
        $this->name = $name;
        rec("  name: {$name}");

        // Features
        $features = val('features', $data);
        if (is_array($features) && count($features)) {
            foreach ($features as $feature => $featureData) {
                $this->registerFeature($feature, $featureData);
            }
        }

        Event::fire('registered');

        // Let the mirror know that registration was successful
        $this->send('registered');
    }

    /**
     * Handle mirror catchup
     *
     * @param Message $message
     */
    public function message_catchup(Message $message) {
        Event::fire('catchup');
    }

    /**
     * TEST: send mirror to sleep
     *
     * @param Message $message
     */
    public function message_sleepme(Message $message) {
        rec(" mirror asked to go to sleep");
        sleep(1);
        $this->sleep();
    }

    /**
     * TEST: send mirror to sleep
     *
     * @param Message $message
     */
    public function message_wakeme(Message $message) {
        rec(" mirror asked to be woken up");
        sleep(1);
        $this->wake();
    }

    /**
     * Register a feature on this mirror
     *
     * @param string $feature
     * @param array $data
     */
    protected function registerFeature($feature, $data) {
        switch ($feature) {
            case 'weather':
                // Test input
                $fields = ['city', 'latitude', 'longitude', 'units'];
                if (count(array_intersect($fields, array_keys($data))) != count($fields)) {
                    return $this->error("failed to register '{$feature}': requires ".implode(',',$fields));
                }

                $city = val('city', $data);
                rec("  registered feature: {$feature} ({$city})");
                $this->features[$feature] = $data;
                break;

            case 'time':
                // Test input
                $timezone = val('timezone', $data);
                if (!$timezone) {
                    return $this->error("failed to register '{$feature}': requires timezone");
                }

                rec("  registered feature: {$feature} ({$timezone})");
                $this->features[$feature] = $data;
                break;

            case 'news':
                // Test input
                $limit = val('limit', $data);
                if (!$limit) {
                    return $this->error("failed to register '{$feature}': requires limit");
                }

                rec("  registered feature: {$feature} ({$limit} stories)");
                $this->features[$feature] = $data;
                break;

            default:
                rec("  unsupported feature: {$feature}");
                break;
        }
    }

    /**
     * Event hook: backend query
     *
     * When the backend wants to performs an update, it fires 'query' and
     * collects feature data from all connected mirrors.
     *
     * @param string $feature
     * @return array
     */
    public function query($feature) {
        return val($feature, $this->features);
    }

    /**
     * Event hook: backend update
     *
     * When the backend has performed an update, it fires 'update' to offer the
     * data back to mirrors.
     *
     * @param string $feature
     * @param mixed $data
     */
    public function update($feature, $data) {
        rec("data updated: {$feature}");
        switch ($feature) {
            case 'weather':
            case 'time':
            case 'news':
                $this->send('update', [
                    'feature' => $feature,
                    'data' => $data
                ]);
                break;

            default:
                break;
        }
    }

    /**
     * Put mirror to sleep
     *
     */
    public function sleep() {
        rec(" mirror going to sleep");

        $tzName = val('timezone', $this->config);
        $tz = new \DateTimeZone($tzName);
        $date = new \DateTime('now', $tz);
        $time = $date->getTimestamp();

        // Report on and erase wake time
        $wokeAt = apcu_fetch('mirror-awake');
        if ($wokeAt) {
            $waking = $time - $wokeAt;
            $wokeDate = new \DateTime('now', $tz);
            $date->setTimestamp($wokeAt);
            $wokeSince = $wokeDate->format('Y-m-d H:i:s');
            rec("  awake since {$wokeSince} ({$waking} seconds)");
            apcu_delete('mirror-awake');
        }

        // Set sleep time
        apcu_add('mirror-sleeping', $time);

        $this->send('sleep');
    }

    /**
     * Wake mirror up
     *
     */
    public function wake() {
        rec(" mirror waking up");

        $tzName = val('timezone', $this->config);
        $tz = new \DateTimeZone($tzName);
        $date = new \DateTime('now', $tz);
        $time = $date->getTimestamp();

        // Report on and erase sleep time
        $sleptAt = apcu_fetch('mirror-sleeping');
        if ($sleptAt) {
            $sleeping = $time - $sleptAt;
            $sleptDate = new \DateTime('now', $tz);
            $date->setTimestamp($sleptAt);
            $sleptSince = $sleptDate->format('Y-m-d H:i:s');
            rec("  slept since {$sleptSince} ({$sleeping} seconds)");
            apcu_delete('mirror-sleeping');
        }

        // Set wake time
        apcu_add('mirror-awake', $time);

        $this->send('wake');
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

        $dimAfter = val('dimafter', $this->config);
        apcu_store('mirror-lockdim', 1, $dimAfter);

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

        $dimLock = apcu_fetch('mirror-lockdim');
        if ($dimLock) {
            return;
        }

        // No motion for $dimAfter seconds, sleep
        $this->sleep();

    }

    /**
     * Shutdown mirror
     *
     * This method is called when the mirror disconnects from the server.
     */
    public function shutdown() {
        rec(" mirror shutdown: {$this->name}");

        $hooks = $this->getHooks();

        // Remove hooks for this module
        foreach ($hooks as $event => $signature) {
            rec("  unregistered hook for '{$event}' -> {$signature}");
            Event::unhook($event, $signature);
        }
    }

}