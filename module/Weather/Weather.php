<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\Module\Weather;

use Alice\Module;

use Alice\Command\Session;

/**
 * ALICE Module: Weather
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 * @version 1.0
 */
class Weather extends Module {

    /**
     * Start module
     *
     */
    public function start() {
        $this->hook('phrase', [$this, 'phrase']);
    }

    /**
     * Handle unattached session phrases
     *
     * @param Session $session
     */
    public function phrase(Session $session) {

    }

}