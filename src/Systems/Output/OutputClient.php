<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\Systems\Output;

use Alice\Alice;

use Alice\Common\Event;

use Alice\Source\Source;

use Alice\Socket\SocketMessage;
use Alice\Server\SocketClient;

/**
 * ALICE Output instance
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
class OutputClient extends SocketClient {

    /**
     * Output settings (from client)
     * @var array
     */
    protected $settings;

    /**
     * Client source
     * @var Source
     */
    protected $source;


    public function __construct(\Ratchet\ConnectionInterface $connection) {
        parent::__construct($connection);

        Event::hook('output_alert', [$this, 'output_alert']);
        Event::hook('output_tts', [$this, 'output_tts']);
    }

    /**
     * Handle output registrations
     *
     * @param SocketMessage $message
     */
    public function message_register(SocketMessage $message) {
        $data = $message->getData();
        return $this->registerClient($data);
    }

    /**
     * Register output client
     *
     * @param array $data
     * @return boolean
     */
    protected function registerClient($data) {
        $this->rec(" output client registering");

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

        // Let the output know that registration was successful
        $this->sendMessage('registered');

        // Let output know how to listen for streamed data
        $stream = Alice::go()->config()->get("output.stream");
        $this->sendMessage('stream', $stream);
    }

    /**
     * Hook to handle "output_alert" events
     *
     * Sends a message to the attached audio device to play the provided alert
     * type.
     *
     * @param string $type
     */
    public function output_alert($type) {
        $this->sendMessage('alert', [
            'type' => $type
        ]);
    }

    /**
     * Hook to handle "output_tts" events
     *
     * Sends a message to the attached audio device to play the provided TTS
     * string.
     *
     * @param string $text
     * @param string $eventID
     */
    public function output_tts($text, $eventID = null) {
        $this->sendMessage('tts', [
            'text' => $text,
            'event' => $eventID
        ]);
    }

    /**
     * Handle played event
     *
     * @param SocketMessage $message
     */
    public function message_played(SocketMessage $message) {
        $event = $message->getData();
        $eventID = val('event', $event);
        Event::fire('output_played', [$eventID]);
    }

}