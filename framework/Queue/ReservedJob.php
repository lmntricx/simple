<?php

namespace Simple\Queue;

/**
 * A job a worker has taken off the queue. $attempts includes the current one.
 */
final class ReservedJob
{
    public function __construct(
        public readonly int|string $id,
        public readonly string $queue,
        public readonly string $payload,
        public readonly int $attempts,
    ) {
    }

    public function name(): string
    {
        return json_decode($this->payload, true)['job'] ?? 'unknown';
    }
}
