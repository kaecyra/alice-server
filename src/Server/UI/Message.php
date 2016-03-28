<?php

namespace Alice\Server\UI;

class Message {

    protected $from;
    protected $message;

    public function __construct($from, $message) {
        $this->from = $from;
        $this->message = $message;

        rec("ui client message:");
        rec($from);
        rec($message);
    }

}