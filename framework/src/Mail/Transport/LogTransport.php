<?php

namespace Simple\Mail\Transport;

use Simple\Mail\Message;

/**
 * Development driver: appends a readable copy of each email to a log file
 * instead of sending it, so you can copy links out of it.
 */
final class LogTransport implements Transport
{
    public function __construct(private string $file)
    {
    }

    public function send(Message $message): void
    {
        $dir = dirname($this->file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $entry = sprintf(
            "==== %s ====\nFrom: %s\nTo: %s\nSubject: %s\n\n%s\n\n",
            date('Y-m-d H:i:s'),
            $message->from['address'] ?? '',
            implode(', ', $message->recipients()),
            $message->subject,
            $message->text,
        );

        file_put_contents($this->file, $entry, FILE_APPEND | LOCK_EX);
    }
}
