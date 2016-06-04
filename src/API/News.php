<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\API;

/**
 * ALICE News API Wrapper
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
class News extends API {

    const API = 'newsapi';

    /**
     * Get news
     *
     * @param string $filter
     * @param array $connectorConfig
     * @param array $sourceDefinition
     */
    public static function get($filter, $connectorConfig, $sourceDefinition) {

        self::rec("requesting updated news data");

        $source = val('source', $sourceDefinition);
        $sourceConfig = val("configuration", $sourceDefinition, []);

        $unifiedConfig = array_merge($sourceConfig, $connectorConfig);
        switch ($source) {
            case 'nyt':
                return self::getNYT($filter, $unifiedConfig);

            case 'reddit':
                return self::getReddit($filter, $unifiedConfig);
        }
        return false;
    }

    /**
     * Get news from reddit
     *
     * @param string $filter
     * @param array $unifiedConfig
     * @return array|false
     */
    public static function getReddit($filter, $unifiedConfig) {

        self::rec(" fetching news from reddit");

        $host = val('host', $unifiedConfig);
        $required = val('limit', $unifiedConfig, 6);
        $width = val('width', $unifiedConfig, 70);

        // Get filter-specific config settings
        $filterConfig = valr("filters.{$filter}", $unifiedConfig);
        $path = val('path', $filterConfig);

        // Get new HttpClient
        $api = self::getClient();
        $api->setBaseUrl($host);

        $response = $api->get($path);

        if ($response->isResponseClass('2xx')) {
            $newsData = $response->getBody();
            $results = valr('data.children', $newsData);

            $news = [];
            foreach ($results as $result) {
                if ($result['kind'] != 't3') {
                    continue;
                }
                if (count($news) >= $required) {
                    break;
                }

                $data = $result['data'];

                $title = html_entity_decode($data['title']);
                if (strlen($title) > $width) {
                    $nearestSpace = strpos($title, ' ', $width - 3);
                    if ($nearestSpace) {
                        $title = substr($title, 0, strpos($title, ' ', $width - 3)).'...';
                    }
                }

                $news[] = [
                    'title' => $title,
                    'url' => $data['url'],
                    'source' => $data['domain'],
                    'id' => $data['name'],
                    'author' => $data['author']
                ];
            }

            return [
                'count' => count($news),
                'articles' => $news
            ];
        } else {
            $errorBody = $response->getBody();
            $error = val('error', $errorBody, 'unknown error');
            self::rec(sprintf("failed to retrieve: %d (%s)", $response->getStatusCode(), $error));
        }
        return false;
    }

    /**
     * Get news from NYT
     *
     * @param string $filter
     * @param array $unifiedConfig
     * @return array|false
     */
    public static function getNYT($filter, $unifiedConfig) {

        self::rec(" fetching news from nyt");

        $host = val('host', $unifiedConfig);
        $key = val('key', $unifiedConfig);
        $required = val('limit', $unifiedConfig, 6);
        $width = val('width', $unifiedConfig, 70);

        // Get filter-specific config settings
        $filterConfig = valr("filters.{$filter}", $unifiedConfig);
        $path = val('path', $filterConfig);
        $arguments = val('arguments', $filterConfig);

        // Get new HttpClient
        $api = self::getClient();
        $api->setBaseUrl($host);

        $response = $api->get($path, array_merge($arguments, [
            'api-key' => $key
        ]));

        if ($response->isResponseClass('2xx')) {
            $newsData = $response->getBody();
            $results = val('results', $newsData);

            $news = [];
            foreach ($results as $result) {
                if ($result['item_type'] != 'Article') {
                    continue;
                }
                if (count($news) >= $required) {
                    break;
                }

                $title = $result['title'];
                if (strlen($title) > $width) {
                    $title = substr($title, 0, strpos($title, ' ', $width - 3)).'...';
                }

                $news[] = [
                    'title' => $result['title'],
                    'url' => $result['url'],
                    'source' => $result['source'],
                    'id' => sha1($result['url'])
                ];
            }

            return [
                'count' => count($news),
                'articles' => $news
            ];
        }

        return false;
    }

}