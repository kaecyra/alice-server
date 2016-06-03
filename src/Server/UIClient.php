<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\Server;

Use Alice\Alice;
use Alice\Server\SocketClient;

use Alice\Source\Want;
use Alice\Source\Source;

/**
 * ALICE Socket UI Client
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
abstract class UIClient extends SocketClient {

    /**
     * Data Wants
     * @var array<Want>
     */
    protected $connectors;

    /**
     * Sensor Wants
     * @var array<Want>
     */
    protected $sensors;


    public function __construct(\Ratchet\ConnectionInterface $connection) {
        parent::__construct($connection);

        $this->connectors = [];
        $this->sensors = [];
    }

    /**
     * Queue data sources
     *
     * @param array $connectors
     */
    protected function queueDataConnectors($connectors) {
        $this->queueGenericConnectors($connectors, Source::CLASS_DATA);
    }

    /**
     * Queue sensor sources
     *
     * @param array $sensors
     */
    protected function queueSensorConnectors($sensors) {
        $this->queueGenericConnectors($sensors, Source::CLASS_SENSOR);
    }

    /**
     * Queue connectors
     *
     * @param array $connectors
     * @param string $class
     */
    protected function queueGenericConnectors($connectors, $class) {
        if (is_array($connectors) && count($connectors)) {
            $this->rec("queuing {$class} connectors");
            $required = [];
            switch ($class) {
                case Source::CLASS_DATA:
                    $required = ['type', 'filter'];
                    break;
                case Source::CLASS_SENSOR:
                    $required = ['type', 'id'];
                    break;
            }
            foreach ($connectors as $connector) {
                $ok = true;
                foreach ($required as $requiredField) {
                    if (!array_key_exists($requiredField, $connector)) {
                        $this->rec($connector);
                        $this->sendError(" ignoring malforumed connector ({$class}:{$connector['type']}), no '{$requiredField}'");
                        $ok = false;
                        break;
                    }
                }

                if ($ok) {
                    $this->queueConnector($connector, $class);
                }
            }
        }
    }

    /**
     * Register a data connector on this client
     *
     * @param array $connector
     * @param string $class type of connector (data, sensor)
     * @return string|boolean:true string error or boolean true success
     */
    protected function queueConnector($connector, $class) {
        $sourceType = $connector['type'];
        if ($class == Source::CLASS_SENSOR) {
            $connector['filter'] = $connector['id'];
        }
        $sourceFilter = $connector['filter'];

        // Get DataWant instance
        $want = Alice::go()->aggregator()->loadWant($class, $sourceType, $sourceFilter);
        $want->setConfig($connector);
        Alice::go()->aggregator()->queueWant($want, [$this, 'prepareWant']);
        $this->rec(" queued connector: ".$want->getUID());
    }

    /**
     * Prepare resolved Want
     *
     * @param Want $want
     * @return boolean|string
     */
    public function prepareWant(Want $want) {
        switch ($want->getClass()) {
            case Source::CLASS_DATA:
                $prepared = $this->prepareDataWant($want);
                break;

            case Source::CLASS_SENSOR:
                $prepared = $this->prepareSensorWant($want);
                break;
        }
        if ($prepared !== true) {
            return $prepared;
        }

        $want = Alice::go()->aggregator()->addWant($want);
        if (!$want) {
            return "could not add connector to aggregator";
        }

        $wantID = $want->getID();
        switch ($want->getClass()) {
            case Source::CLASS_DATA:
                // Register data collection event hook
                $this->hook($want->getEventID(), [$this, "update"]);

                // Remember want
                $this->connectors[$wantID] = $want;
                break;

            case Source::CLASS_SENSOR:
                // Register sensor event hook
                $this->hook($want->getEventID(), [$this, "sense"]);

                // Remember want
                $this->sensors[$wantID] = $want;
                break;
        }

        return true;
    }

    /**
     *
     * @param Want $want
     */
    protected function prepareDataWant(Want $want) {
        $connector = $want->getConfig();
        $want->setConfig(null);

        $requiredFields = $want->getRequiredFields();

        $fieldAliases = [
            'city' => 'location.city',
            'units' => 'location.units',
            'latitude' => 'location.latitude',
            'longitude' => 'location.longitude'
        ];

        $connectorConfig = val('config', $connector, []);
        if (!is_array($connectorConfig)) {
            $connectorConfig = [];
        }

        // Allow intelligent re-use of client config settings for data connectors
        foreach ($requiredFields as $requiredField) {
            // If this setting isn't defined in the connector config
            if (!array_key_exists($requiredField, $connectorConfig)) {
                // And we know how to find it in the client config
                if (array_key_exists($requiredField, $fieldAliases)) {
                    // Load it from there
                    $connectorConfig[$requiredField] = valr($fieldAliases[$requiredField], $this->settings);
                }
            }

            if (!array_key_exists($requiredField, $connectorConfig)) {
                return "missing config '{$requiredField}'";
            }
        }

        // Push config to DataWant and aggregate
        $want->setConfig($connectorConfig);
        return true;
    }

    /**
     *
     * @param Want $want
     */
    protected function prepareSensorWant(Want $want) {
        return true;
    }

}