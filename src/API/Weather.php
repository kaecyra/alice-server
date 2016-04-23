<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\API;

use Garden\Http\HttpClient;

use Exception;

/**
 * ALICE Weather API Wrapper
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
class Weather {

    /**
     * Get weather
     *
     * @param array $ui
     * @param array $config
     * @param HttpClient $api
     */
    public static function get($ui, $config, HttpClient $api) {

        $city = val('city', $ui);
        rec(" requesting updated weather data: {$city}");

        $source = val('source', $config);
        $sourceConfig = valr("sources.{$source}", $config);
        switch ($source) {
            case 'forecast':
                return Weather::getForecast($ui, $sourceConfig, $api);
        }
        return false;
    }

    /**
     *
     * @param array $ui
     * @param array $config
     * @param HttpClient $api
     */
    public static function getForecast($ui, $config, HttpClient $api) {

        rec("  fetching news from forecast.io");

        $latitude = val('latitude', $ui);
        $longitude = val('longitude', $ui);
        $units = val('units', $ui);

        $host = val('host', $config);
        $path = val('path', $config);
        $key = val('key', $config);

        $path = formatString($path, [
            'api' => $key,
            'latitude' => $latitude,
            'longitude' => $longitude
        ]);

        $api->setBaseUrl($host);

        $response = $api->get($path, [
            'units' => $units,
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
            rec("  {$temp} degrees C ({$weather['now']}, {$weather['today']})");

            return $weather;
        }
        return false;
    }

}