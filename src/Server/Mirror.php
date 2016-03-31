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

    public function __construct(\Ratchet\ConnectionInterface $connection) {
        $this->connection = $connection;

        Event::hook('data_query', [$this, 'query']);
        Event::hook('data_update', [$this, 'update']);
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

        // Let the mirror know that registration was successful
        $this->send('registered');
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
                $fields = ['city', 'citycode', 'units'];
                if (count(array_intersect($fields, array_keys($data))) != count($fields)) {
                    return $this->error("failed to register '{$feature}': requires ".implode(',',$fields));
                }

                $city = val('city', $data);
                rec("  registered feature: {$feature} ({$city})");
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
     * Shutdown mirror
     *
     * This method is called when the mirror disconnects from the server.
     */
    public function shutdown() {
        rec(" mirror shutdown: {$this->name}");
    }

}