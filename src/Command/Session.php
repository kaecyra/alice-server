<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\Command;

use Alice\Common\Store;
use Alice\Common\Event;
use Alice\Common\State;

use Alice\Source\Sensor\Audio;

use Alice\Command\Phrase;

use Alice\Systems\Output\Output;

/**
 * ALICE Command Session
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
class Session  {

    const SESSION_ACTIVE = 'active';
    const SESSION_COMPLETE = 'complete';
    const SESSION_FAILED = 'failed';
    const SESSION_TIMEOUT = 'timeout';

    /**
     * Session ID
     * @var string
     */
    protected $id;

    /**
     * List of session commands
     * @var array<Phrase>
     */
    protected $commands;

    /**
     * Session creation time
     * @var integer
     */
    protected $created;

    /**
     * Session state
     * @var string
     */
    protected $session;

    /**
     * String parser
     * @var State
     */
    protected $state;

    /**
     * Data store
     * @var Store
     */
    protected $store;

    /**
     * Audio source
     * @var Audio
     */
    protected $source;

    /**
     * Array of known hooks
     * @var array
     */
    protected $hooks;

    /**
     * Session handler (callback handling this session)
     * @var callbable
     */
    protected $handler;

    /**
     * Pending audio event
     * @var string
     */
    protected $wait;

    /**
     * Last phrase
     * @var Phrase
     */
    protected $last;

    /**
     * Create new command session
     *
     */
    public function __construct() {
        $this->id = 'command-session-'.uniqid('', true);
        $this->commands = [];
        $this->created = time();
        $this->session = self::SESSION_ACTIVE;
        $this->state = new State();
        $this->store = new Store();
        $this->handler = null;

        // Hook to handle played events
        $this->hook('output_played', [$this, 'played']);
    }

    /**
     * Test whether session is timed out
     *
     * @param integer $timeout
     */
    public function isExpired($timeout) {
        // Session ended normally
        if ($this->session != self::SESSION_ACTIVE) {
            return true;
        }

        // Session timed out
        if (($this->created + $timeout) >= time()) {
            $this->setState(self::SESSION_TIMEOUT);
            return true;
        }

        return false;
    }

    /**
     * Test if session is handled
     *
     * @return boolean
     */
    public function isHandled() {
        if (!is_null($this->handler)) {
            return true;
        }
        return false;
    }

    /**
     * Set session handler
     *
     * @param callable $handler
     */
    public function setHandler($handler) {
        $this->handler = $handler;
    }

    /**
     * Get state reference
     *
     * @return State
     */
    public function getState() {
        return $this->state;
    }

    /**
     * Get last phrase
     *
     * @return Phrase
     */
    public function getLast() {
        return $this->last;
    }

    /**
     * Get store reference
     *
     * @return Store
     */
    public function getStore() {
        return $this->store;
    }

    /**
     * Set audio source
     *
     * @param Audio $source
     */
    public function attachSource(Audio $source) {
        $this->source = $source;
    }

    /**
     * Change session state
     *
     * @param string $session
     */
    public function setSession($session) {
        $this->session = $session;
    }

    /**
     * Receive phrase
     *
     * @param string $phrase
     * @param float $confidence
     */
    public function update($phrase, $confidence) {

        // Register phrase
        $phrase = new Phrase($this, $phrase, $confidence);
        $phraseID = $phrase->getID();
        $this->phrases[$phraseID] = $phrase;
        $this->last = $phrase;

        // Notify environment
        if ($this->isHandled()) {
            call_user_func($this->handler, $this);
        } else {
            Event::fire('phrase', [$this]);
        }

        $this->state = $phrase->parse($this->state);
    }

    /**
     * Handle output_played event
     *
     * @param string $eventID
     * @return type
     */
    public function played($eventID) {
        if (!is_array($this->wait) || $this->wait['event'] != $eventID) {
            return;
        }
        $wait = $this->wait;
        $this->wait = null;

        switch ($wait['then']) {
            case 'listen':
                $this->listen();
                break;
        }
    }

    /**
     * Say something
     *
     * @param string $text
     * @return string event ID
     */
    public function say($text) {
        $eventID = Output::tts($text);
        return $eventID;
    }

    /**
     * Ask a question
     *
     * @param string $text
     * @return string event ID
     */
    public function ask($text) {
        $eventID = $this->say($text);
        $this->wait = [
            'event' => $eventID,
            'then' => 'listen'
        ];
    }

    /**
     * Ask the source to listen
     *
     *
     */
    public function listen() {
        $this->source->listen($this->id);
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
     * Shutdown
     *
     */
    public function __destruct() {
        $hooks = $this->getHooks();

        // Remove hooks for this module
        foreach ($hooks as $event => $signature) {
            Event::unhook($event, $signature);
        }
    }

}