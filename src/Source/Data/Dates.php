<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\Source\Data;

use Alice\Source\DataSource;

use Alice\Common\Store;

/**
 * ALICE DataSource: Dates
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
class Dates extends DataSource {

    //const FREQUENCY = 43200;
    const FREQUENCY = 300;

    const DATE_CACHE_TTL = 3600;

    /**
     * Date cache
     * @var Store
     */
    protected $store;

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
            'dates'
        ];

        $this->requirements = [
            'base' => [
                'timezone',
                'locale'
            ]
        ];

        $this->store = new Store;
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
     * Fetch updated calendar
     *
     * @param array $config
     */
    public function fetch($filter, $config) {
        if (!$this->haveDates($filter)) {
            $this->prepareDates($filter, $config);
        }

        $rdates = $this->getDatesFor($filter, $config);
        return [
            'count' => count($rdates),
            'dates' => $rdates
        ];
    }

    /**
     * Check if we have date data for filter
     *
     * @param string $filter
     */
    public function haveDates($filter) {
        if (!$this->store->get($filter)) {
            return false;
        }

        $filterExpires = $this->store->get("{$filter}.expires");
        if (time() > $filterExpires) {
            return false;
        }

        return true;
    }

    /**
     * Prepare dates for filter
     *
     * [
     *   {
     *     "name": <string>,
     *     "date": <string>"YYYY-MM-DD",
     *     "type": <string>"annual",
     *     "duration": <string>"1 day",
     *     "message": <string>
     *   }[, ...]
     * ]
     *
     * @param string $filter
     * @param array $config
     */
    public function prepareDates($filter, $config) {
        $filterConfig = valr("configuration.filters.{$filter}", $this->config);
        $dataSource = val('source', $filterConfig);

        $data = [];
        switch ($dataSource) {
            case 'file':
                $fileName = val('file', $filterConfig);
                $filePath = paths(APP_ROOT, 'conf', $fileName);
                $fileData = file_get_contents($filePath);
                $data = json_decode($fileData, true);
                break;
        }

        $tz = new \DateTimeZone($config['timezone']);
        $today = new \DateTime('now', $tz);

        // Iterate over dates and set istart and iend
        $dates = [];
        foreach ($data as $date) {
            $date['id'] = substr(sha1($date['name']), 0, 12);

            switch ($date['type']) {
                case 'daily':
                    $str = $today->format('Y-m-d')." ".$date['date'];
                    $date['datetime'] = $dateO = new \DateTime($str, $tz);
                    break;
                case 'annual':
                    $date['datetime'] = $dateO = new \DateTime($date['date'], $tz);
                    break;
            }

            $date['istart'] = $dateO->format('U');
            $period = \DateInterval::createFromDateString($date['duration']);
            $dateO->add($period);
            $date['iend'] = $dateO->format('U');
            $dates[] = $date;
        }

        $this->store->set($filter, [
            'dates' => $dates,
            'expires' => time() + self::DATE_CACHE_TTL
        ]);
    }

    /**
     * Get active "dates" for a specific date
     *
     * @param string $filter
     * @param array $config
     * @param string $date
     * @return type
     */
    public function getDatesFor($filter, $config, $date = null) {
        if (is_null($date)) {
            $date = 'now';
        }

        $tz = new \DateTimeZone($config['timezone']);
        $dateTime = new \DateTime($date, $tz);
        $dateEpoch = $dateTime->format('U');

        $trimFields = [
            'id' => true,
            'name' => true,
            'date' => true,
            'message' => true,
            'fmessage' => true,
            'event' => true
        ];

        $dates = [];
        $filterDates =  $this->store->get("{$filter}.dates");
        foreach ($filterDates as $filterDate) {
            if ($dateEpoch < $filterDate['istart'] || $dateEpoch > $filterDate['iend']) {
                continue;
            }

            $customFields = [];

            $filterDate['delta'] = $delta = $dateTime->diff($filterDate['datetime'], true);
            switch ($filterDate['type']) {
                case 'annual':
                    $filterDate['years'] = $delta->format("%y");
                    $filterDate['ordinal'] = self::ordinal($filterDate['years']);
                    $customFields = array_merge($customFields, [
                        'years' => true,
                        'ordinal' => true
                    ]);
                    break;
            }

            $filterDate['fmessage'] = $this->formatMessage($filterDate);

            $dates[] = array_intersect_key($filterDate, array_merge($trimFields, $customFields));
        }

        return $dates;
    }

    /**
     * Get string ordinal for number
     *
     * @param integer $number
     * @return string
     */
    protected static function ordinal($number) {
        $ends = array('th','st','nd','rd','th','th','th','th','th','th');
        if ((($number % 100) >= 11) && (($number%100) <= 13)) {
            return $number. 'th';
        } else {
            return $number. $ends[$number % 10];
        }
    }

    /**
     * Return formatted string
     *
     * @param array $date
     * @return string
     */
    protected function formatMessage($date) {
        return formatString($date['message'], $date);
    }

}