<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\Source\Data;

use Alice\Alice;
use Alice\Source\DataSource;

use \ZMQ;

use \DateTime;

/**
 * ALICE DataSource: Messages
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
class Messages extends DataSource {

    const FREQUENCY = 20;

    /**
     * Storage TTL (days)
     * @var integer
     */
    const DEFAULT_STORAGE_TTL = 2;

    /**
     * Default display ttl (seconds)
     * @var integer
     */
    const DEFAULT_DISPLAY_TTL = 3600;

    /**
     * ZeroMQ context
     * @var \ZMQContext
     */
    protected $context;

    /**
     * ZeroMQ data socket
     * @var \ZMQSocket
     */
    protected $zero;

    /**
     * ZeroMQ sync socket
     * @var \ZMQSocket
     */
    protected $zerosync;

    /**
     * List of currently cached messages
     * @var array
     */
    protected $messages;

    /**
     * Actual storage ttl
     * @var integer
     */
    protected $storageTTL;

    /**
     * Allowed contacts
     * @var array
     */
    protected $contacts;

    /**
     * Constructor
     *
     * @param string $type
     * @param array $config
     */
    public function __construct($type, $config) {
        parent::__construct($type, $config);

        $this->frequency = self::FREQUENCY;

        $this->provides = [
            'messages'
        ];

        $this->requirements = [
            'base' => [
                'timezone',
                'limit'
            ]
        ];

        $this->messages = [];

        $this->storageTTL = valr('configuration.ttl', $config, self::DEFAULT_STORAGE_TTL) * 86400;

        // Load contacts
        $this->loadContacts();

        try {
            $this->rec("binding zero socket");
            $this->context = new \React\ZMQ\Context(Alice::loop());

            $zmqConfig = Alice::go()->config()->get('data.zero');

            $zmqDataDSN = "tcp://{$zmqConfig['host']}:{$zmqConfig['port']}";
            $this->rec(" data dsn: {$zmqDataDSN}");

            $zmqSyncDSN = "tcp://{$zmqConfig['host']}:{$zmqConfig['syncport']}";
            $this->rec(" sync dsn: {$zmqSyncDSN}");

            // Bind receive socket
            $this->zero = $this->context->getSocket(ZMQ::SOCKET_SUB);
            $this->zero->bind($zmqDataDSN);
            $this->zero->subscribe('sms-message');
            $this->zero->on('messages', [$this, 'getMessage']);

            // Bind sync socket
            $this->zerosync = $this->context->getSocket(ZMQ::SOCKET_REP);
            $this->zerosync->bind($zmqSyncDSN);
            $this->zerosync->on('message', [$this, 'syncMessage']);

            $this->zero->on('error', function ($e) {
                $this->rec($e);
            });

        } catch (Exception $ex) {
            $this->rec(print_r($ex, true));
        }
    }

    /**
     *
     */
    public function loadContacts() {

        $this->contacts = [];

        $fileName = 'messages-contacts.json';
        $filePath = paths(APP_ROOT, 'conf/data', $fileName);
        $fileData = file_get_contents($filePath);
        $data = json_decode($fileData, true);

        foreach ($data as $contact) {
            $contactID = $contact['number'];
            $this->contacts[$contactID] = $contact;
        }

    }

    /**
     * Build DataWant ID
     *
     * @param string $filter
     * @param array $config
     * @return string
     */
    public function buildWantID($filter, $config) {
        return "{$this->class}:{$this->type}/{$filter}";
    }

    /**
     * Inbound ZMQ sync message
     *
     * @param string $message
     */
    public function syncMessage($message) {
        $this->rec("received sync: {$message}");
        $this->zerosync->send("synced");
    }

    /**
     * Inbound ZMQ message
     *
     * @param string|array $message
     */
    public function getMessage($message, $topic = null) {
        if (is_array($message)) {
            $topic = $message[0];
            $message = $message[1];
        }

        if (is_string($message)) {
            $decoded = json_decode($message, true);
            if (!is_array($decoded)) {
                $message = [
                    'message' => $message
                ];
            } else {
                $message = $decoded;
            }
        }

        if (is_null($topic)) {
            $topic = 'unknown';
        }

        switch ($topic) {
            case 'sms-message':
                $this->receiveSMS($message);
                break;
            default:
                $this->rec("received message [{$topic}]:");
                $this->rec($message);
                break;
        }
    }

    /**
     * Get unique message ID for message
     *
     * @param array $message
     * @return string
     */
    public function getMessageID($message) {
        $from = val('from', $message);
        $to = val('to', $message);
        $date = val('date', $message);
        $messageHash = sha1($message['message']);

        $combo = implode(' - ', [$from, $to, $date, $messageHash]);
        return sha1($combo);
    }

    /**
     * Receive inbound SMS
     *
     * @param array $message
     */
    public function receiveSMS($message) {
        $from = val('from', $message);
        $text = val('message', $message);

        // Lookup contact
        $contactID = substr($from, -10);
        if (!array_key_exists($contactID, $this->contacts)) {
            $this->rec("discarded sms from unknown contact [from {$from}]: {$text}");
            return;
        }
        $contact = $this->contacts[$contactID];
        $fromName = $contact['name'];

        $this->rec("received sms [from {$fromName} ({$from})]: {$text}");

        $messageID = $this->getMessageID($message);
        $message['id'] = $messageID;
        $message['contact'] = $contact;

        if (!array_key_exists($messageID, $this->messages)) {
            $keepUntil = time() + $this->storageTTL;
            $message['keep'] = $keepUntil;
            $message['received'] = time();
            $this->messages[$messageID] = $message;

            $this->setWake(true);
        } else {
            $this->rec(" message is a duplicate, ignoring");
        }
    }

    /**
     * Fetch messages
     *
     * @param string $filter
     * @param array $config
     */
    public function fetch($filter, $config) {
        $this->cull();
        $messages = $this->filter(val('ttl', $config, self::DEFAULT_DISPLAY_TTL));

        $limit = val('limit', $config);
        $messages = array_slice($messages, 0, $limit);

        $tz = new \DateTimeZone($config['timezone']);

        foreach ($messages as &$message) {
            $date = new DateTime($message['date'], $tz);
            $message['time'] = $this->getTimeAgo($date);
        }

        return [
            'count' => count($messages),
            'messages' => $messages
        ];
    }

    /**
     * Cull expired messages
     *
     * Delete messages from queue when they are older than the storage TTL.
     */
    public function cull() {
        $messageIDs = array_keys($this->messages);
        foreach ($messageIDs as $messageID) {
            $message = $this->messages[$messageID];
            if (time() > $message['keep']) {
                $this->rec("culling expired sms: {$messageID}");
                unset($this->messages[$messageID]);
            }
        }
    }

    /**
     * Get messages younger than TTL
     *
     * @param integer $ttl
     */
    public function filter($ttl) {
        $messages = [];

        // Watch array in reverse order for efficiency
        $set = array_reverse($this->messages);
        foreach ($set as $message) {
            if ((time() - $message['received']) < $ttl) {
                $messages[] = $message;
                continue;
            }

            // Break at first non matching message
            break;
        }

        return $messages;
    }

    /**
     * Get namespaced cache key
     *
     * @param string $key
     * @return string
     */
    protected function getCacheKey($key) {
        return sprintf($key, $this->name);
    }

    /**
     * Get fuzzy time ago
     *
     * @param DateTime $date
     * @return string
     */
    protected function getTimeAgo($date) {
        $now = new DateTime('now', $date->getTimezone());
        $now = $now->getTimestamp();

        $ago = $now - $date->getTimestamp();
        if ($ago < 60) {
            $when = round($ago);
            $s = ($when == 1) ? "second" : "seconds";
            return "$when $s ago";
        } elseif ($ago < 3600) {
            $when = round($ago / 60);
            $m = ($when == 1) ? "minute" : "minutes";
            return "$when $m ago";
        } elseif ($ago >= 3600 && $ago < 86400) {
            $when = round($ago / 60 / 60);
            $h = ($when == 1) ? "hour" : "hours";
            return "$when $h ago";
        } else {
            return strtolower($date->format('l, g:ia'));
        }
    }

}