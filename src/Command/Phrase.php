<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\Command;

use Alice\Alice;
use Alice\Common\Event;

use Alice\Command\State;

/**
 * ALICE Command Phrase
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
class Phrase  {

    /**
     *
     * @var type
     */
    protected $id;


    protected $phrase;


    protected $confidence;

    /**
     * Create phrase
     *
     * @param string $phrase
     * @param float $confidence
     */
    public function __construct($phrase, $confidence) {
        $this->id = 'command-phrase-'.uniqid('', true);
        $this->phrase = $phrase;
        $this->confidence = $confidence;
    }

    /**
     * Get phrase
     *
     * @return string
     */
    public function getPhrase() {
        return $this->phrase;
    }

    /**
     * Get confidence
     *
     * @return float
     */
    public function getConfidence() {
        return $this->confidence;
    }

    /**
     * Parse phrase
     * 
     * @param State $state
     * @return State
     */
    public function parse(State $state) {
        return $state->parse($this->phrase);
    }

}