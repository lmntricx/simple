<?php

namespace Simple\Queue;

/**
 * Records dispatched jobs instead of running them. Queue::fake() in tests:
 *
 *   $queue = Queue::fake();
 *   ...
 *   $this->assertCount(1, $queue->pushed(SendInvoice::class));
 */
final class FakeQueue
{
    /** @var array<int, array{job: Job, delay: int, connection: ?string}> */
    public array $jobs = [];

    public function record(Job $job, int $delay, ?string $connection): void
    {
        $this->jobs[] = ['job' => $job, 'delay' => $delay, 'connection' => $connection];
    }

    /**
     * @template T of Job
     * @param class-string<T> $class
     * @param (\Closure(T): bool)|null $filter
     * @return T[]
     */
    public function pushed(string $class, ?\Closure $filter = null): array
    {
        $jobs = array_map(fn ($entry) => $entry['job'], array_filter($this->jobs, fn ($entry) => $entry['job'] instanceof $class));

        return array_values($filter ? array_filter($jobs, $filter) : $jobs);
    }

    /**
     * Run the recorded jobs now (handy to test what they do).
     */
    public function runAll(): void
    {
        $jobs = $this->jobs;
        $this->jobs = [];

        foreach ($jobs as $entry) {
            app()->call([$entry['job'], 'handle']);
        }
    }
}
