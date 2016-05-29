<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\API;

/**
 * ALICE Weather API Wrapper
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
class Weather extends API {

    const API = 'weatherapi';

    /**
     * Get weather
     *
     * @param string $filter
     * @param array $connectorConfig
     * @param array $sourceDefinition
     */
    public static function get($filter, $connectorConfig, $sourceDefinition) {

        $city = val('city', $connectorConfig);
        self::rec("requesting updated weather data: {$city}");

        $source = val('source', $sourceDefinition);
        $sourceConfig = val("configuration", $sourceDefinition, []);

        $unifiedConfig = array_merge($sourceConfig, $connectorConfig);
        switch ($source) {
            case 'forecast':
                return self::getForecast($filter, $unifiedConfig);
        }
        return false;
    }

    /**
     * Get weather from Forecast
     *
     * @param string $filter
     * @param array $unifiedConfig
     */
    public static function getForecast($filter, $unifiedConfig) {

        self::rec(" fetching weather from forecast.io");

        $latitude = val('latitude', $unifiedConfig);
        $longitude = val('longitude', $unifiedConfig);
        $units = val('units', $unifiedConfig);

        $apiUnits = 'auto';
        switch ($units) {
            case 'metric':
                $apiUnits = 'ca';
                break;

            case 'imperial':
                $apiUnits = 'us';
                break;
        }

        $host = val('host', $unifiedConfig);
        $key = val('key', $unifiedConfig);

        // Get filter-specific config settings
        $filterConfig = valr("filters.{$filter}", $unifiedConfig);
        $path = val('path', $filterConfig);

        $path = formatString($path, [
            'api' => $key,
            'latitude' => $latitude,
            'longitude' => $longitude
        ]);

        // Get new HttpClient
        $api = self::getClient();
        $api->setBaseUrl($host);

        $response = $api->get($path, [
            'units' => $apiUnits,
            'exclude' => 'daily'
        ]);

        if ($response->isResponseClass('2xx')) {
            $weatherData = $response->getBody();

            $current = val('currently', $weatherData);
            $minute = val('minutely', $weatherData);
            $hourly = val('hourly', $weatherData);

            $summary = val('summary', $hourly);
            $minSummary = val('summary', $minute);

            $weather = array_merge($current, [
                'summary' => rtrim($current['summary'], '.'),
                'now' => rtrim($minSummary, '.'),
                'today' => rtrim($summary, '.')
            ]);

            $round = [
                'temperature' => 0,
                'apparentTemperature' => 0,
                'dewPoint' => 0,
                'visibility' => 1
            ];
            foreach ($round as $roundKey => $roundPrecision) {
                $weather[$roundKey] = round($weather[$roundKey], $roundPrecision);
            }

            $percent = [
                'humidity',
                'cloudCover',
                'precipProbability'
            ];
            foreach ($percent as $percentKey) {
                $weather[$percentKey] = round($weather[$percentKey] * 100, 0);
            }

            $temp = val('temperature', $weather);
            self::rec(" {$temp} degrees C ({$weather['now']}, {$weather['today']})");

            return $weather;
        } else {
            $errorBody = $response->getBody();
            $error = val('error', $errorBody, 'unknown error');
            self::rec(sprintf("failed to retrieve: %d (%s)", $response->getStatusCode(), $error));
        }
        return false;
    }

}