<?php

namespace Alice\Server\Hardware;

class Message {

    protected $from;
    protected $message;

    public function __construct($from, $message) {
        $this->from = $from;
        $this->message = $message;

        rec("hardware client message:");
        rec($from);
        rec($message);
    }

}