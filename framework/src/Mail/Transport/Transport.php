<?php

namespace Simple\Mail\Transport;

use Simple\Mail\Message;

interface Transport
{
    public function send(Message $message): void;
}
