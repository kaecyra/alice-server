<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\Http;

use \Alice\Common\Config;

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

use \Slim\App;

/**
 * ALICE Http UI router
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
class WebUI extends App {

    const DEVICE_KEY_FORMAT = '%s/%s';

    /**
     * WebUI global config
     * @var array
     */
    protected $config;

    /**
     * List of devices indexed by type/id key
     * @var array
     */
    protected $devices;

    /**
     * App constructor
     *
     * @param array $slimConfig
     */
    public function __construct($slimConfig = null) {
        parent::__construct($slimConfig);
        $this->config = Config::file(paths(PATH_CONFIG, 'config.json'), true);

        // Index all known devices
        $this->indexDevices();

        $this->get('/mirror/{id}', [$this, 'ui_mirror']);
        $this->post('/message', [$this, 'web_message']);
    }

    /**
     * Parse config into device list
     *
     */
    protected function indexDevices() {
        $this->devices = [];
        $devices = $this->config->get('devices');
        foreach ($devices as $device) {
            $type = val('type', $device, 'unknown');
            $id = val('id', $device, 'unknown');
            $deviceKey = sprintf(self::DEVICE_KEY_FORMAT, $type, $id);

            $this->devices[$deviceKey] = $device;
        }
    }

    /**
     * Look up a device by type and id
     *
     * @param string $type
     * @param string $id
     */
    protected function getDevice($type, $id) {
        $deviceKey = sprintf(self::DEVICE_KEY_FORMAT, $type, $id);
        return val($deviceKey, $this->devices, false);
    }

    /**
     * Draw the mirror html
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function ui_mirror(Request $request, Response $response) {
        $type = 'mirror';
        $id = $request->getAttribute('id');
        $device = $this->getDevice($type, $id);

        if ($device === false) {
            throw new \Exception("unknown '{$type}' device '{$id}'");
        }

        $viewPath = paths(PATH_TEMPLATES, 'mirror.tpl');
        $viewData = file_get_contents($viewPath);

        // Prepare data
        $serverConfig = $this->config->get('server');
        $serverConfig = array_merge($serverConfig, val('server', $device, []));

        // Prepare view
        $viewData = formatString($viewData, [
            't'         => time(),
            'device'    => json_encode($device, JSON_PRETTY_PRINT),
            'server'    => json_encode($serverConfig, JSON_PRETTY_PRINT)
        ]);

        // Render
        $response->getBody()->write($viewData);

        return $response;
    }

    /**
     * Handle SMS message callbacks
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function web_message(Request $request, Response $response) {
        // Render
        $response->getBody()->write("ok");

        return $response;
    }

}