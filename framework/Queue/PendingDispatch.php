<?php

namespace Simple\Queue;

/**
 * Returned by Job::dispatch() so options can be chained:
 *   SendReport::dispatch($user)->delay(300)->onQueue('reports');
 * The job is pushed when this object is destroyed (end of the statement).
 */
final class PendingDispatch
{
    private int $delay = 0;
    private ?string $connection = null;
    private bool $pushed = false;

    public function __construct(private Job $job)
    {
    }

    /** Seconds before the job may run. */
    public function delay(int $seconds): self
    {
        $this->delay = max(0, $seconds);
        return $this;
    }

    public function onQueue(string $queue): self
    {
        $this->job->queue = $queue;
        return $this;
    }

    public function onConnection(string $connection): self
    {
        $this->connection = $connection;
        return $this;
    }

    public function __destruct()
    {
        if (!$this->pushed) {
            $this->pushed = true;
            Queue::push($this->job, $this->delay, $this->connection);
        }
    }
}
