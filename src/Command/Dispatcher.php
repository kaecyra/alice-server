<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\Command;

use Alice\Alice;
use Alice\Common\Event;

use Alice\Systems\Output\Output;

/**
 * ALICE Command Dispatcher
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
class Dispatcher  {

    const SESSION_TIMEOUT = 300;

    /**
     * Current sessions
     * @var array
     */
    protected $sessions;

    /**
     * Retrieve a session
     *
     * @param string $sessionID
     * @return Session
     */
    public function getSession($sessionID) {
        if (array_key_exists($sessionID, $this->sessions)) {
            return $this->sessions[$sessionID];
        }
        return false;
    }

    /**
     * Store a session
     *
     * @param Session $session
     * @return Session
     */
    public function storeSession(Session $session) {
        $sessionID = $session->getID();
        $this->sessions[$sessionID] = $session;
        return $session;
    }

    /**
     * Delete a session
     *
     * @param string $sessionID
     */
    public function deleteSession($sessionID) {
        unset($this->sessions[$sessionID]);
    }

    /**
     * Create a new stored session
     *
     * @return Session
     */
    public function createSession() {
        $session = new Session();
        $this->storeSession($session);
        return $session;
    }

    /**
     * Discard completed or timed out sessions
     *
     * 
     */
    public function cleanup() {
        $sessionIDs = array_keys($this->sessions);
        foreach ($sessionIDs as $sessionID) {
            if ($this->getSession($sessionID)->isExpired(self::SESSION_TIMEOUT)) {
                $this->deleteSession($sessionID);
            }
        }
    }

}