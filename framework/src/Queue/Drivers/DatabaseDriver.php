<?php

namespace Simple\Queue\Drivers;

use Simple\Database\Connection;
use Simple\Database\QueryBuilder;
use Simple\Queue\ReservedJob;

/**
 * Stores jobs in the "jobs" table. Safe with several workers: a job is claimed
 * with a conditional UPDATE, so only one worker can win it.
 */
final class DatabaseDriver implements Driver
{
    /**
     * @param int $retryAfter seconds after which a reserved job is assumed abandoned
     *                        (its worker crashed) and handed out again. Keep it longer
     *                        than your slowest job.
     */
    public function __construct(
        private string $table = 'jobs',
        private int $retryAfter = 90,
        private ?string $connection = null,
    ) {
    }

    public function push(string $queue, string $payload, int $delay = 0): int|string
    {
        $now = time();

        $id = $this->query()->insert([
            'queue' => $queue,
            'payload' => $payload,
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => $now + max(0, $delay),
            'created_at' => $now,
        ]);

        return is_numeric($id) ? (int) $id : $id;
    }

    public function pop(array $queues): ?ReservedJob
    {
        foreach ($queues as $queue) {
            // A few tries: another worker may claim the job we picked first.
            for ($try = 0; $try < 5; $try++) {
                $now = time();

                $row = $this->query()
                    ->where('queue', $queue)
                    ->where(fn (QueryBuilder $q) => $q
                        ->where(fn (QueryBuilder $q) => $q->whereNull('reserved_at')->where('available_at', '<=', $now))
                        ->orWhere('reserved_at', '<=', $now - $this->retryAfter))
                    ->orderBy('id')
                    ->first();

                if ($row === null) {
                    break;
                }

                $claimed = $this->query()
                    ->where('id', $row['id'])
                    ->where('attempts', $row['attempts'])
                    ->update(['reserved_at' => $now, 'attempts' => (int) $row['attempts'] + 1]);

                if ($claimed === 1) {
                    return new ReservedJob($row['id'], $row['queue'], $row['payload'], (int) $row['attempts'] + 1);
                }
            }
        }

        return null;
    }

    public function delete(ReservedJob $job): void
    {
        $this->query()->where('id', $job->id)->delete();
    }

    public function release(ReservedJob $job, int $delay = 0): void
    {
        $this->query()->where('id', $job->id)->update([
            'reserved_at' => null,
            'available_at' => time() + max(0, $delay),
        ]);
    }

    public function size(string $queue): int
    {
        return $this->query()->where('queue', $queue)->count();
    }

    public function clear(string $queue): int
    {
        return $this->query()->where('queue', $queue)->delete();
    }

    private function query(): QueryBuilder
    {
        return new QueryBuilder(Connection::get($this->connection), $this->table);
    }
}
