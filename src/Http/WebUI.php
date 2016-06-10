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

use \ZMQ;
use \ZMQContext;

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
        $this->post('/message/receipt', [$this, 'web_message_receipt']);
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

        // Get and parse message contents
        $messageData = $request->getBody()->getContents();
        $messageParts = [];
        parse_str($messageData, $messageParts);

        // Prepare message for zmq pipe
        $message = [
            'to' => val('to', $messageParts),
            'from' => val('msisdn', $messageParts),
            'message' => val('text', $messageParts),
            'date' => val('message-timestamp', $messageParts)
        ];

        try {
            $zmqConfig = $this->config->get('data.zero');

            $context = new ZMQContext();

            // Publisher socket
            $zmqDataDSN = "tcp://{$zmqConfig['host']}:{$zmqConfig['port']}";
            $dataSocket = $context->getSocket(ZMQ::SOCKET_PUB);
            $dataSocket->connect($zmqDataDSN);

            // Synchronize socket
            $zmqSyncDSN = "tcp://{$zmqConfig['host']}:{$zmqConfig['syncport']}";
            $syncSocket = $context->getSocket(ZMQ::SOCKET_REQ);
            $syncSocket->connect($zmqSyncDSN);
            $syncSocket->send('sync');
            $syncSocket->recv();

            // Send message
            $update = json_encode($message);
            $dataSocket->sendMulti(['sms-message', $update]);

            $dataSocket->close();
            $syncSocket->close();

        } catch (Exception $ex) {

        }

        // Render
        $response->getBody()->write("ok");

        return $response;
    }

    /**
     * Handle SMS message callbacks
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function web_message_receipt(Request $request, Response $response) {
        // Render
        $response->getBody()->write("ok");

        return $response;
    }

}