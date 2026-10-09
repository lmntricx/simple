<?php

namespace Simple\Queue\Drivers;

use Simple\Queue\ReservedJob;

interface Driver
{
    /**
     * @return int|string the new job's id
     */
    public function push(string $queue, string $payload, int $delay = 0): int|string;

    /**
     * Reserve the next available job, checking $queues in order (first = highest priority).
     *
     * @param string[] $queues
     */
    public function pop(array $queues): ?ReservedJob;

    public function delete(ReservedJob $job): void;

    /**
     * Put a job back to be retried after $delay seconds.
     */
    public function release(ReservedJob $job, int $delay = 0): void;

    public function size(string $queue): int;

    /** Delete every job on $queue; returns how many. */
    public function clear(string $queue): int;
}
