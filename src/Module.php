<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice;

use Alice\Alice;

use Alice\Common\Event;

/**
 * ALICE Module Base
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
abstract class Module {

    // Toggles
    const TOGGLE_ON = 'on';
    const TOGGLE_OFF = 'off';

    /**
     * Module name
     * @var string
     */
    protected $module;

    /**
     * Module info
     * @var array
     */
    protected $info;

    /**
     * List of events that this module has hooked
     * @var array
     */
    protected $hooks;

    private function __construct() {
        $this->hooks = [];
        $this->info = [];
    }

    /**
     * Get an instance of this module
     *
     * @param array $info module info
     * @return \static
     */
    public static function instance($info) {
        $instance = new static();
        $instance->module = val('name', $info);
        $instance->info = $info;
        $instance->start();
        return $instance;
    }

    /**
     * Module initializer
     */
    abstract public function start();

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
        rec("   registered hook for '{$event}' -> {$signature}");
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
     * Get prefixed data from central store
     *
     * @param string $setting
     * @param mixed $default
     * @return mixed
     */
    public function get($setting, $default = null) {
        return Alice::go()->getStore()->get("moduleconfig.{$this->module}.{$setting}", $default);
    }

    /**
     * Get unprefixed data from central store
     *
     * @param string $setting
     * @param mixed $default
     * @return mixed
     */
    public function getGlobal($setting, $default = null) {
        return Alice::go()->getStore()->get($setting, $default);
    }

    /**
     * Store prefixed data in central store
     *
     * @param string $setting
     * @param mixed $value
     * @return mixed
     */
    public function set($setting, $value) {
        return Alice::go()->getStore()->set("moduleconfig.{$this->module}.{$setting}", $value);
    }

    /**
     * Store unprefixed data in central store
     *
     * @param string $setting
     * @param mixed $value
     * @return mixed
     */
    public function setGlobal($setting, $value) {
        return Alice::go()->getStore()->set($setting, $value);
    }

    /**
     * Get config
     * @return \Config
     */
    public function config() {
        return Alice::go()->config();
    }

    /**
     * Tagged log message
     *
     * Emit a log message tagged with the module name.
     *
     * @param string $message
     */
    public function rec($message, $level = \Daemon\Daemon::LOG_L_APP, $options = null) {
        if (is_array($message) || is_object($message)) {
            $message = print_r($message, true);
        }
        rec("[{$this->module}] {$message}", $level, $options);
    }

    /**
     * Get reference to module
     *
     * @param string $name
     * @return Module
     */
    public static function ref($name) {
        return Alice::go()->modules()->get($name);
    }

    /**
     * Call a module method
     *
     * @param string $name
     * @param string $method
     * @return mixed
     */
    public static function call($name, $method) {
        $manager = Alice::go()->modules();

        // Don't shit ourselves if the module doesn't exist
        $isLoaded = $manager->isLoaded($name);
        if (!$isLoaded) {
            return null;
        }

        $module = $manager->get($name);
        $arguments = array_slice(func_get_args(), 2);
        return call_user_func_array([$module, $method], $arguments);
    }

}
