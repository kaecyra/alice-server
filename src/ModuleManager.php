<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice;

use Alice\Common\Config;
use Alice\Common\Event;

use Alice\Module;

/**
 * ALICE Module Manager
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
class ModuleManager {

    /**
     * List of source folders
     * @var array
     */
    protected $sources;

    /**
     * Module information
     * @var array
     */
    protected $modules;

    /**
     * Modules list
     * @var array
     */
    protected $enabled;

    /**
     * Start module manager
     *
     */
    public function __construct() {
        $this->sources = [];
        $this->modules = [];
        $this->enabled = [];
    }

    /**
     * Add module scan source
     *
     * @param string $sourceDir
     * @return ModuleManager
     */
    public function addSource($sourceDir) {
        if (is_dir($sourceDir)) {
            $this->sources[] = $sourceDir;
        }
        return $this;
    }

    /**
     * Wire up modules
     *
     * @param Config $config
     * @throws DaemonException
     */
    public function start(Config $config) {

        // Scan all modules
        $this->scanAll();

        // Load all activated modules
        $modules = $config->get('modules');
        $this->loadAll($modules);
    }

    /**
     * Scan all modules
     *
     * @return void
     */
    public function scanAll() {

        rec("  scanning module source folders");
        $this->modules = [];

        foreach ($this->sources as $sourcePath) {
            rec("   source: {$sourcePath}");

            $modulesList = scandir($sourcePath);
            foreach ($modulesList as $moduleDirName) {
                // Ignore hidden files and directory traversal links
                $char = substr($moduleDirName, 0, 1);
                if ($char == '.') {
                    continue;
                }

                $moduleDir = paths($sourcePath, $moduleDirName);
                $moduleFile = paths($moduleDir, ucfirst($moduleDirName).'.php');

                // Read module info
                $moduleInfo = $this->scan($moduleFile);
                if (!$moduleInfo) {
                    continue;
                }

                $moduleName = val('name', $moduleInfo);
                $moduleVersion = val('version', $moduleInfo);
                rec("   scanned module: {$moduleName} (v{$moduleVersion})");

                $this->modules[$moduleName] = $moduleInfo;
            }
        }
    }

    /**
     * Scan a module file
     *
     * @param string $file path to module class
     * @return boolean
     */
    public function scan($file) {
        if (!file_exists($file)) {
            return false;
        }

        $fileName = basename($file);
        $fileParts = explode('.', $fileName);
        $moduleName = strtolower($fileParts[0]);
        $moduleNamespace = '\\Alice\\Module\\';

        // Prepare module info array
        $info = [
            'name' => $moduleName,
            'namespace' => $moduleNamespace,
            'file' => $file,
            'requires' => []
        ];

        // Loop over file lines and scan for module info
        $infoBuffer = false;
        $mode = null;
        $modes = ['intro', 'description'];
        $lines = file($file);
        foreach ($lines as $line) {
            $line = trim($line);

            // Find start of class
            if ($infoBuffer && substr($line,0,5) == 'class') {
                preg_match('`^class ([\w\_\d]+) extends`', $line, $matches);
                $className = $matches[1];
                $info['class'] = $info['namespace'].$className;
                break;
            }

            // Only read comment lines
            if ($infoBuffer && substr($line,0,1) != '*') {
                continue;
            }

            if ($infoBuffer) {
                // Change mode on blank lines
                if ($line == '*') {
                    $mode = array_shift($modes);
                    continue;
                }

                if (preg_match('/^\* @([\w]+)\b(.*)/i', $line, $matches)) {
                    $key = trim($matches[1]);
                    $value = trim($matches[2]);
                    if ($key == 'uses') {
                        $info['requires'][] = $value;
                    } else {
                        $info[$key] = $value;
                    }
                    continue;
                } else if (preg_match('/^\* (.*)/i', $line, $matches)) {
                    $info[$mode] = trim(val($mode, $info, '') . "\n" . $matches[1]);
                }
            }

            if (!$infoBuffer && trim($line) == '/**') {
                $infoBuffer = true;
                $mode = array_shift($modes);
                continue;
            }
        }

        // Check that we got everything we need
        $requiredKeys = ['file', 'name', 'intro', 'author', 'version'];
        $requiredMatch = array_fill_keys($requiredKeys, null);

        $requirementCheck = array_intersect_key($info, $requiredMatch);
        if (sizeof($requirementCheck) < sizeof($requiredKeys)) {
            rec("   module '{$moduleName}' failed to scan: missing fields");

            $missing = array_diff_key($requiredMatch, $info);
            foreach ($missing as $missingKey => $trash) {
                rec("    - {$missingKey}");
            }
            return false;
        }

        $info['dir'] = dirname($info['file']);

        return $info;
    }

    /**
     * Load all modules per the autoload
     *
     */
    public function loadAll($modules) {

        // Include and instantiate active modules
        $this->enabled = [];
        foreach ($modules as $moduleName => $moduleState) {

            // Ignore 'off' modules
            if ($moduleState != 'on') {
                continue;
            }
            $moduleName = strtolower($moduleName);
            $this->load($moduleName);
        }
    }

    /**
     * Load a module
     *
     * @param string $name Module name
     * @return boolean load success status
     */
    public function load($name, $level = 0) {
        $nest = str_repeat(' ', $level);
        $name = stringEndsWith(strtolower($name), 'module', true, true);
        rec("{$nest}  load module: {$name}");

        // Check if we've got a module called this
        $info = $this->info($name);
        if (!$info) {
            rec("{$nest}   unknown module, not loaded");
            return false;
        }

        // Check requirements
        $requiredModules = val('requires', $info);
        if (!is_array($requiredModules)) {
            $requiredModules = [];
        }

        // If we have requirements, try to load them
        if (count($requiredModules)) {
            $txtRequirements = implode(',', $requiredModules);
            rec("{$nest}   module has requirements: {$txtRequirements}");

            // Keep track of which requirements we've loaded so we can unload if things went wrong
            $loadedRequirements = [];

            // Loop over requirements and load each one in turn
            $loadedAllRequirements = true;
            foreach ($requiredModules as $requiredModule) {
                if (!$this->isLoaded($requiredModule)) {

                    // Try to load module if available
                    $loadedRequirement = false;
                    if ($this->isAvailable($requiredModule)) {
                        $loadedRequirement = $this->load($requiredModule, $level+1);
                    }

                    $loadedAllRequirements &= $loadedRequirement;

                    if (!$loadedRequirement) {
                        rec("{$nest}   failed loading required module: {$requiredModule}");
                        return false;
                    }

                    $loadedRequirements[] = $requiredModule;
                }
            }

            // Loading failed, so unload successfully loaded requirements
            if (!$loadedAllRequirements) {
                rec("{$nest}  failed loading requirements, unloading");
                foreach ($loadedRequirements as $loadedModule) {
                    $this->unload($loadedModule);
                }
            }
        }

        // If the module is already around, unload it first (this is essentially a reload)
        if ($this->isLoaded($name)) {
            $this->unload($name);
        }

        $moduleFile = $info['file'];

        // Load the sourcecode
        $className = $info['class'];
        $preLoaded = class_exists($className);

        if ($preLoaded) {
            if (extension_loaded('runkit')) {
                runkit_import($moduleFile, RUNKIT_IMPORT_OVERRIDE | RUNKIT_IMPORT_CLASSES | RUNKIT_IMPORT_FUNCTIONS);
            }
        } else {
            require_once($moduleFile);
        }

        // Create module instance
        $this->enabled[$name] = $className::instance($info);
        rec("{$nest}   loaded module: {$name}");

        return true;
    }

    /**
     * Unload a module
     *
     * @param string $name Module name
     */
    public function unload($name) {
        $name = stringEndsWith(strtolower($name), 'module', true, true);
        rec("  unload module: {$name}");

        if (!$this->isLoaded($name)) {
            rec("   module '{$name}' not yet loaded");
            return false;
        }

        $module = $this->get($name);
        $hooks = $module->getHooks();

        // Remove hooks for this module
        foreach ($hooks as $event => $signature) {
            rec("   unregistered hook for '{$event}' -> {$signature}");
            Event::unhook($event, $signature);
        }

        // Unset this module
        unset($this->enabled[$name]);

        return true;
    }

    /**
     * Check if a module is loaded
     *
     * @param string $name
     * @return boolean
     */
    public function isLoaded($name) {
        $name = stringEndsWith(strtolower($name), 'module', true, true);

        // Check if this module is enabled
        if (!array_key_exists($name, $this->enabled)) {
            return false;
        }

        if (!($this->enabled[$name] instanceof Module)) {
            return false;
        }

        return true;
    }

    /**
     * Check if a module is available for loading
     *
     * @param string $name
     * @return boolean
     */
    public function isAvailable($name) {
        $name = stringEndsWith(strtolower($name), 'module', true, true);

        // Check if this module is available
        if (!array_key_exists($name, $this->modules)) {
            return false;
        }

        return true;
    }

    /**
     * List [active] modules
     *
     * @param boolean $active Optionally list only active modules
     * @return array
     */
    public function modules($active = true) {
        if ($active) {
            return array_keys($this->enabled);
        }
        return array_keys($this->modules);
    }

    /**
     * Get module info
     *
     * @param string $name
     * @return array
     */
    public function info($name) {
        $name = stringEndsWith(strtolower($name), 'module', true, true);
        return val($name, $this->modules, false);
    }

    /**
     * Get an instance of a module
     *
     * @param string $name
     * @return Module
     * @throws ModuleException
     */
    public function get($name) {
        $name = stringEndsWith(strtolower($name), 'module', true, true);

        // Check if module exists first
        if (!array_key_exists($name, $this->enabled) || !($this->enabled[$name] instanceof Module)) {
            return false;
        }

        return $this->enabled[$name];
    }

}

class ModuleException extends \Exception {

}
