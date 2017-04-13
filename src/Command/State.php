<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\Command;

use Alice\Common\Event;

/**
 * ALICE Command State
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
class State implements ArrayAccess {

    /**
     * Internal data store
     * @var array
     */
    protected $container;

    /**
     * Constructor
     *
     * @param array $initial optional
     */
    private function __construct($initial = []) {
        if (!is_array($initial)) {
            $initial = [
                'targets' => [],
                'method' => null,
                'toggle' => null
            ];
        }
        $this->container = $initial;
        $this->clean();
    }

    /**
     * Initialize state and parse
     *
     * @param string $command
     * @return State $state
     */
    public static function create($command) {

        /*
         * Tokenized floating detection
         */

        $state = new State();
        return $state->parse($command);
    }

    /**
     * Prepare state matcher for matching
     */
    public function clean() {
        $this->container = array_merge($this->container, [
            'gather' => false,
            'consume' => false,
            'token' => null,
            'last_token' => null,
            'tokens' => 0,
            'parsed' => 0
        ]);
    }

    /**
     * Parse another command into this state
     *
     * @param string $command
     * @return State
     */
    public function parse($command) {
        $this->clean();
        $this->container['command'] = $command;
        return State::analyze($this);
    }

    /**
     * Get initial command string
     *
     * @return string
     */
    public function command() {
        return $this->container['command'];
    }

    /**
     * Get parsed method
     *
     * @return string
     */
    public function method() {
        return val('method', $this->container, null);
    }

    /**
     * Test possible phrase on command string
     *
     * @param string $test
     * @param boolean $case optional. case sensitive. default false
     * @return boolean
     */
    public function match($test, $case = false) {
        $flags = '';
        if (!$case) {
            $flags = 'i';
        }
        return (boolean)preg_match("`{$test}`{$flags}", $this->container['command']);
    }

    /**
     * Test if state has token(s)
     *
     * @param array|string $tokens
     * @param boolean $all optional. require all tokens. default false (any token)
     */
    public function have($tokens, $all = false) {
        if (!is_array($this->container['pieces']) || !count($this->container['pieces'])) {
            return false;
        }

        if (!is_array($tokens)) {
            $tokens = [$tokens];
        }

        foreach ($tokens as $token) {
            if (in_array($token, $this->container['pieces'])) {
                if (!$all) {
                    return true;
                }
            } else {
                if ($all) {
                    return false;
                }
            }
        }

        // If we get here with $all, we have all tokens
        if ($all) {
            return true;
        }

        // If we get here, we don't have all and we got no tokens
        return false;
    }

    /**
     * Get token/piece by index
     *
     * @param integer $index
     * @return string|null
     */
    public function index($index) {
        return val($index, $this->container['pieces']);
    }

    /**
     * Get data by key
     *
     * @param string $key
     */
    public function &__get ($key) {
        return $this->container[$key];
    }

    /**
     * Assigns value by key
     *
     * @param string $key
     * @param mixed $value value to set
     */
    public function __set($key, $value) {
        $this->container[$key] = $value;
    }

    /**
     * Whether or not data exists by key
     *
     * @param string $key to check for
     * @return boolean
     */
    public function __isset($key) {
        return isset($this->container[$key]);
    }

    /**
     * Unset data by key
     *
     * @param string $key
     */
    public function __unset($key) {
        unset($this->container[$key]);
    }

    /**
     * Check if offset exists
     *
     * @param mixed $offset
     */
    public function offsetExists($offset) {
        return isset($this->container[$offset]);
    }

    /**
     * Set value on offset
     *
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value) {
        if (is_null($offset)) {
            $this->container[] = $value;
        } else {
            $this->container[$offset] = $value;
        }
    }

    /**
     * Get offset value
     *
     * @param mixed $offset
     */
    public function offsetGet($offset) {
        return isset($this->container[$offset]) ? $this->container[$offset] : null;
    }

    /**
     * Unset offset
     *
     * @param mixed $offset
     */
    public function offsetUnset($offset) {
        unset($this->container[$offset]);
    }

    /**
     * Internal analyze method
     *
     * @param State $state
     * @return State
     */
    protected static function analyze($state) {

        $state->pieces = explode(' ', $state->command);
        $state->parts = $state['pieces'];

        // Scan phase, allows gathering unprefixed tokens
        foreach ($state->parts as $part) {
            $state = Event::fireFilter('tokenscan', $state, [$part]);
        }

        $state->nextToken();

        while ($state->token !== null) {

            // GATHER

            if ($state->gathering()) {

                $loop = [];

                $loop['node'] = valr('gather.node', $state);
                $loop['type'] = strtolower(valr('gather.type', $state, $loop['node']));
                $loop['multi'] = valr('gather.multi', $state, false);

                $loop['firstpass'] = valr('gather.first_pass', $state, true);
                $state->gather['first_pass'] = false;

                if (isset($state->gather['fast'])) {
                    unset($state->gather['fast']);
                }

                // Detect boundaries
                $boundaries = valr('gather.boundary', $state, null);
                if ($boundaries) {
                    if (!is_array($boundaries)) {
                        $boundaries = [$boundaries];
                    }

                    if (count($boundaries)) {
                        foreach ($boundaries as $boundary) {
                            if ($state->token == $boundary) {
                                $state->addTarget($loop['node'], $state->gather['delta'], $loop['multi']);
                                $state->gather = false;
                                $loop['type'] = false;
                                break;
                            }
                        }
                    }
                }

                switch ($loop['type']) {

                    case 'phrase':

                        $terminators = ['"' => true];
                        $terminator = $state->checkTerminator((($loop['firstpass']) ? $terminators : null));

                        // Add space if there's something in Delta already
                        if (strlen($state->gather['delta'])) {
                            $state->gather['delta'] .= ' ';
                        }
                        $state->gather['delta'] .= $state->token;
                        $state->consume();

                        // Check if this is a phrase
                        $isList = val('list', $state->gather, false);
                        $terminator = val('terminator', $state->gather, false);
                        if (!$terminator && strlen($state->gather['delta'])) {
                            $checkPhrase = trim($state->gather['delta']);

                            // Allow lists
                            if ($isList && substr($state->token, -1) == ',') {
                                break;
                            }

                            $checkPhrase = trim($state->gather['delta']);
                            $state->gather = false;
                            $state->addTarget($loop['node'], $checkPhrase, $loop['multi']);
                            break;
                        }
                        break;

                    case 'page':
                    case 'number':

                        // Add token
                        if (strlen($state->gather['delta'])) {
                            $state->gather['delta'] .= ' ';
                        }
                        $state->gather['delta'] .= $state->token;
                        $state->consume();

                        // If we're closed, close up
                        $currentDelta = trim($state->gather['delta']);
                        if (strlen($currentDelta) && is_numeric($currentDelta)) {
                            $state->gather = false;
                            $state->addTarget($loop['node'], $currentDelta, $loop['multi']);
                            break;
                        }
                        break;

                    // Hook for custom tokens
                    default:
                        $state = Event::fireFilter('tokengather', $state, [$loop]);
                        break;
                }

                if (!strlen($state->token)) {
                    $state->gather = false;
                    continue;
                }

            } else {

                // Fire stem gathering
                $state = Event::fireFilter('stems', $state);

                // Fire method gathering
                $state = Event::fireFilter('methods', $state);

                // Fire enhancements
                $state = Event::fireFilter('enhancements', $state);

                /*
                 * FOR, BECAUSE
                 */

                if (in_array($state->compare_token, ['for', 'because', 'that'])) {
                    $state->consumeUntilNextKeyword('for', false, true);
                }

                /*
                 * Allow consume overrides in plugins
                 */
                $state = Event::fireFilter('token', $state);

                // Allow fast gathering!
                if ($state->gathering() && val('fast', $state->gather)) {
                    continue;
                }

                /*
                 * Consume keywords into current cache if one exists
                 */
                $state->consumeUntilNextKeyword();
            }

            // Get a new token
            $state->nextToken();

            // End token loop
        }

        unset($state['parts']);

        /*
         * PARAMETERS
         */

        // Terminate any open gathers
        if ($state->gather) {
            $loop['node'] = $state->gather['node'];
            $state->addTarget($loop['node'], $state->gather['delta']);
            $state->gather = false;
        }

        // Gather any remaining tokens into the 'gravy' field
        if ($state->method) {
            $gravy = array_slice($state->pieces, $state->tokens);
            $state->gravy = implode(' ', $gravy);
        }

        if ($state->consume) {
            $state->cleanupConsume($state);
        }

        // Parse this resolved state into potential actions
        $state->parseFor();

        return $state;
    }

    /**
     * Are we gathering?
     *
     * @return boolean
     */
    public function gathering() {
        return ($this->gather && !empty($this->gather['node']));
    }

    /**
     * Advance the token
     *
     */
    public function nextToken() {
        $this->last_token = $this->token;
        $this->token = array_shift($this->parts);
        $this->peek = val(0, $this->parts, null);
        $this->compare_token = preg_replace('/[^\w]/i', '', strtolower($this->token));
        if ($this->token) {
            $this->parsed++;
        }
    }

    /**
     * Check for and handle terminators
     *
     * @param array $terminators
     */
    public function checkTerminator($terminators = null) {

        // Detect termination
        $terminator = val('terminator', $this->gather, false);

        if (!$terminator && is_array($terminators)) {
            $testTerminator = substr($this->token, 0, 1);
            if (array_key_exists($testTerminator, $terminators)) {
                $terminator = $testTerminator;
                $this->token = substr($this->token, 1);
                $double = $terminators[$testTerminator];
                if ($double) {
                    $this->gather['terminator'] = $testTerminator;
                }
            }
        }

        if ($terminator) {
            // If a terminator has been registered, and the first character in the token matches, chop it
            if (!strlen($this->gather['delta']) && substr($this->token, 0, 1) == $terminator) {
                $this->token = substr($this->token, 1);
            }

            // If we've found our closing character
            if (($foundPosition = stripos($this->token, $terminator)) !== false) {
                $this->token = substr($this->token, 0, $foundPosition);
                unset($this->gather['terminator']);
            }
        }

        return val('terminator', $this->gather, false);
    }

    /**
     * Consume a token
     *
     * @param string $setting optional.
     * @param mixed $value optional.
     * @param boolean $multi optional. consume multiple instances of this token. default false.
     */
    public function consume($setting = null, $value = null, $multi = false) {
        // Clean up consume in case we're currently running one
        $this->cleanupConsume();

        $this->tokens = $this->parsed;
        if (!is_null($setting)) {
            // Prepare the target
            if ($multi) {
                if (isset($this->$setting)) {
                    if (!is_array($this->$setting)) {
                        $this->$setting = [$this->$setting];
                    }
                } else {
                    $this->$setting = [];
                }
                array_push($this->$setting, $value);
            } else {
                $this->$setting = $value;
            }
        }

        // If we consume a method, discard any stem
        if ($setting == 'method') {
            unset($this->container['stem']);
        }
    }

    /**
     * Consume tokens until we encounter the next keyword
     *
     * @param string $setting optional. start new consumption
     * @param boolean $inclusive whether to include current token or skip to the next
     * @param boolean $multi create multiple entries if the same keyword is consumed multiple times?
     */
    public function consumeUntilNextKeyword($setting = null, $inclusive = false, $multi = false) {

        if (!is_null($setting)) {

            // Cleanup existing Consume
            if ($this->consume !== false) {
                $this->cleanupConsume();
            }

            // What setting are we consuming for?
            $this->consume = [
                'setting' => $setting,
                'cache' => '',
                'multi' => $multi,
                'skip' => $inclusive ? 0 : 1
            ];

            // Prepare the target
            if ($multi) {
                if (isset($this->$setting)) {
                    if (!is_array($this->$setting)) {
                        $this->$setting = [$this->$setting];
                    }
                } else {
                    $this->$setting = [];
                }
            }

            // Never include the actual triggering keyword
            return;
        }

        if ($this->consume !== false) {
            // If Tokens == Parsed, something else already consumed on this run, so we stop
            if ($this->tokens == $this->parsed) {
                $this->cleanupConsume();
                return;
            } else {
                $this->tokens = $this->parsed;
            }

            // Allow skipping tokens
            if ($this->consume['skip']) {
                $this->consume['skip']--;
                return;
            }

            $this->consume['cache'] .= "{$this->token} ";
        }
    }

    /**
     * Add a target
     *
     * @param string $name
     * @param mixed $data
     * @param boolean $multi optional. default false.
     */
    public function addTarget($name, $data, $multi = false) {
        // Prepare the target
        if ($multi) {
            if (isset($this->targets[$name])) {
                if (!is_array($this->targets[$name])) {
                    $this->targets[$name] = [$this->targets[$name]];
                }
            } else {
                $this->targets[$name] = [];
            }
            array_push($this->targets[$name], $data);
        } else {
            $this->targets[$name] = $data;
        }
    }

    /**
     * Test if we have a given target
     *
     * @param string $name
     * @return boolean
     */
    public function haveTarget($name) {
        return (isset($this->targets[$name]) && !empty($this->targets[$name]));
    }

    /**
     * Cleanup consume
     *
     */
    protected function cleanupConsume() {
        if (!$this->consume) {
            return;
        }

        $setting = $this->consume['setting'];
        if ($this->consume['multi']) {
            array_push($this->$setting, trim($this->consume['cache']));
        } else {
            $this->$setting = trim($this->consume['cache']);
        }
        $this->consume = false;
    }

    /**
     * Parse the 'for' keywords into Time and Reason keywords as appropriate
     *
     */
    public function parseFor() {
        if (!isset($this->for)) {
            return;
        }

        $reasons = [];
        $unset = [];
        $fors = sizeof($this->for);
        for ($i = 0; $i < $fors; $i++) {
            $for = $this['for'][$i];
            $tokens = explode(' ', $for);
            if (!sizeof($tokens)) {
                continue;
            }

            // Maybe this is a time! Try to parse it
            if (is_numeric($tokens[0])) {
                $haveTime = false;
                $currentSpan = [];
                $goodSpan = [];
                foreach ($tokens as $forToken) {
                    $currentSpan[] = $forToken;
                    $forString = implode(' ', $currentSpan);

                    if (($time = strtotime("+{$forString}")) !== false) {
                        $haveTime = true;
                        $goodSpan[] = $forToken;
                        $this->time = implode(' ', $goodSpan);
                    }
                }

                if ($haveTime) {
                    $unset[] = $i;
                    continue;
                }
            }

            // Nope, its (part of) a reason
            $unset[] = $i;
            $reasons[] = $for;
        }

        $this->reason = rtrim(implode(' for ', $reasons), '.');

        // Delete parsed elements
        foreach ($unset as $unsetKey) {
            unset($this->for[$unsetKey]);
        }
    }

}