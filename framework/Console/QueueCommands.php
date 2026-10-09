<?php

namespace Simple\Console;

use Simple\Queue\FailedJobs;
use Simple\Queue\Queue;
use Simple\Queue\Worker;

/**
 * queue:work, queue:failed, queue:retry, queue:forget, queue:flush, queue:clear
 */
trait QueueCommands
{
    private function queueWork(array $options): int
    {
        $connection = $this->option($options, 'connection') ?? config('queue.default', 'sync');

        if ($connection === 'sync') {
            return $this->fail('QUEUE_CONNECTION is "sync": jobs already run immediately, there is nothing to work. '
                . 'Set QUEUE_CONNECTION=database (or pass --connection=database).');
        }

        $queues = array_filter(array_map('trim', explode(',', $this->option($options, 'queue') ?? 'default')));
        $once = in_array('--once', $options, true);

        if (!$once) {
            $this->line(sprintf('Processing queue(s) [%s] on [%s]. Press Ctrl+C to stop.', implode(', ', $queues), $connection));
        }

        $this->app->make(Worker::class)->work(Queue::driver($connection), $queues, [
            'once' => $once,
            'stop_when_empty' => in_array('--stop-when-empty', $options, true),
            'sleep' => (int) ($this->option($options, 'sleep') ?? 3),
            'max_jobs' => (int) ($this->option($options, 'max-jobs') ?? 0),
            'max_time' => (int) ($this->option($options, 'max-time') ?? 0),
            'memory' => (int) ($this->option($options, 'memory') ?? 128),
        ], fn (string $line) => $this->line(date('Y-m-d H:i:s') . '  ' . $line));

        return 0;
    }

    private function queueFailed(): int
    {
        $failed = FailedJobs::all();

        if ($failed === []) {
            $this->line('No failed jobs.');
            return 0;
        }

        foreach ($failed as $job) {
            $name = json_decode($job['payload'], true)['job'] ?? '?';
            $error = strtok($job['exception'], "\n");
            $this->line(sprintf('  %-5s %s  %-30s %s', $job['id'], $job['failed_at'], $name, $error));
        }

        $this->line('');
        $this->line('Retry with `php simple queue:retry <id|all>`, remove with `php simple queue:forget <id>`.');

        return 0;
    }

    private function queueRetry(?string $id): int
    {
        if ($id === null) {
            return $this->fail('Usage: php simple queue:retry <id|all>');
        }

        $ids = $id === 'all' ? array_column(FailedJobs::all(), 'id') : [$id];

        foreach ($ids as $failedId) {
            FailedJobs::retry($failedId)
                ? $this->line("Pushed failed job [$failedId] back onto the queue.")
                : $this->fail("No failed job [$failedId].");
        }

        return 0;
    }

    private function queueForget(?string $id): int
    {
        if ($id === null) {
            return $this->fail('Usage: php simple queue:forget <id>');
        }

        if (!FailedJobs::forget($id)) {
            return $this->fail("No failed job [$id].");
        }

        $this->line("Deleted failed job [$id].");
        return 0;
    }

    private function queueFlush(): int
    {
        $this->line('Deleted ' . FailedJobs::flush() . ' failed job(s).');
        return 0;
    }

    private function queueClear(array $options): int
    {
        $connection = $this->option($options, 'connection') ?? config('queue.default', 'sync');
        $queue = $this->option($options, 'queue') ?? 'default';

        if ($connection === 'sync') {
            return $this->fail('The sync connection has no stored jobs.');
        }

        $this->line('Deleted ' . Queue::driver($connection)->clear($queue) . " pending job(s) from [$queue].");
        return 0;
    }
}
