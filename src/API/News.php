<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\API;

use Garden\Http\HttpClient;

use Exception;

/**
 * ALICE News API Wrapper
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
class News {

    /**
     * Get news
     *
     * @param array $ui
     * @param array $config
     * @param HttpClient $api
     */
    public static function get($ui, $config, HttpClient $api) {

        rec(" requesting updated news data");
        
        $source = val('source', $config);
        $sourceConfig = valr("sources.{$source}", $config);
        switch ($source) {
            case 'nyt':
                return News::getNYT($ui, $sourceConfig, $api);

            case 'reddit':
                return News::getReddit($ui, $sourceConfig, $api);
        }
        return false;
    }

    /**
     * Get news from reddit
     *
     * @param array $ui
     * @param array $config
     * @param HttpClient $api
     * @return array|false
     */
    public static function getReddit($ui, $config, HttpClient $api) {

        rec("  fetching news from reddit");

        $host = val('host', $config);
        $path = val('path', $config);

        $api->setBaseUrl($host);

        $response = $api->get($path);

        if ($response->isResponseClass('2xx')) {
            $newsData = $response->getBody();
            $results = valr('data.children', $newsData);

            $required = val('limit', $ui, 6);
            $width = val('width', $ui, 70);

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
                    $title = substr($title, 0, strpos($title, ' ', $width - 3)).'...';
                }

                $news[] = [
                    'title' => html_entity_decode($title),
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
        }
        return false;
    }

    /**
     * Get news from NYT
     *
     * @param array $ui
     * @param array $config
     * @param HttpClient $api
     * @return array|false
     */
    public static function getNYT($ui, $config, HttpClient $api) {

        rec("  fetching news from nyt");

        $host = val('host', $config);
        $path = val('path', $config);
        $key = val('key', $config);

        $api->setBaseUrl($host);

        $arguments = $this->config->get('interact.news.arguments');

        $response = $api->get($path, array_merge($arguments, [
            'api-key' => $key
        ]));

        if ($response->isResponseClass('2xx')) {
            $newsData = $response->getBody();
            $results = val('results', $newsData);

            $required = val('limit', $ui, 6);
            $width = val('width', $ui, 70);

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