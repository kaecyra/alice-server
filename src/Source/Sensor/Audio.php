<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\Source\Sensor;

use Alice\Alice;

use Alice\Source\SensorSource;

use Alice\Systems\Output\Output;

/**
 * ALICE SensorSource: Audio
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
class Audio extends SensorSource {

    /**
     * Constructor
     *
     * @param string $type
     * @param array $config
     */
    public function __construct($type, $config) {
        parent::__construct($type, $config);

        $this->satisfies = [
            'audio'
        ];
    }

    /**
     * Build DataWant ID
     *
     * @param string $filter
     * @param array $config
     * @return string
     */
    public function buildWantID($filter, $config) {
        return "{$this->class}:{$this->type}/{$this->id}";
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
     * Handle audio event
     *
     * @param array $event
     * @return type
     */
    public function pushEvent($event) {
        $sessionID = val('session', $event);
        $type = val('type', $event);
        switch ($type) {
            // Alert that we're listening for sounds
            case 'cue':
                $this->rec('audio input keyword');
                Output::alert(Output::ALERT_START_LISTEN);
                break;

            // Alert that we've finished listening for sounds
            case 'uncue':
                $this->rec('audio input decoding');
                Output::alert(Output::ALERT_NOTIFY);
                break;

            case 'command':
                $this->rec('audio input phrase');

                $phrase = val('phrase', $event);
                $confidence = val('confidence', $event);
                $this->rec(" phrase: {$phrase}");

                // Get or create session
                $session = null;
                if ($sessionID) {
                    $session = Alice::go()->dispatcher()->getSession($sessionID);
                }

                if (!$session) {
                    $session = Alice::go()->dispatcher()->createSession();
                    $session->attachSource($this);
                }

                // Push phrase to sesssion
                $session->update($phrase, $confidence);
                break;
        }

        // Push raw event out
        parent::pushEvent($event);
    }

    /**
     * Ask client to listen
     *
     * @param string $sessionID
     */
    public function listen($sessionID) {
        $this->rec("asking for input: {$sessionID}");
        $this->getClient()->sendMessage('listen', [
            'session' => $sessionID
        ]);
    }

}