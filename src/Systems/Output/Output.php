<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Alice\Systems\Output;

use Alice\Common\Event;

use Exception;

/**
 * ALICE Output
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package alice-server
 */
class Output {

    const ALERT_INFO = 'info';
    const ALERT_NOTIFY = 'notify';
    const ALERT_TONE = 'tone';

    const ALERT_START_LISTEN = 'start_listen';
    const ALERT_STOP_LISTEN = 'stop_listen';

    /**
     * Send an audio alert
     *
     * @param string $type
     */
    public static function alert($type) {
        Event::fire('output_alert', [$type]);
    }

    /**
     * Send TTS data
     *
     * @param string $text
     */
    public static function tts($text) {
        Event::fire('output_tts', [$text]);
    }


    public static function streamFile($file) {

    }


    public static function streamData($data) {

    }


    public static function streamStream($stream) {
        
    }

}