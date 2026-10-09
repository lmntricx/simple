<?php

namespace Simple\Mail\Transport;

use Simple\Mail\Message;

/**
 * Keeps messages in memory. Used by Mail::fake() in tests.
 */
final class ArrayTransport implements Transport
{
    /** @var Message[] */
    public array $messages = [];

    public function send(Message $message): void
    {
        $this->messages[] = $message;
    }

    /** @return Message[] */
    public function sentTo(string $address): array
    {
        return array_values(array_filter(
            $this->messages,
            fn (Message $m) => in_array($address, $m->recipients(), true),
        ));
    }

    public function last(): ?Message
    {
        return $this->messages[count($this->messages) - 1] ?? null;
    }

    public function clear(): void
    {
        $this->messages = [];
    }
}
