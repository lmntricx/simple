<?php

namespace Simple\Queue;

use Simple\Database\Connection;
use Simple\Database\QueryBuilder;
use Throwable;

/**
 * Jobs that used up their attempts, kept in "failed_jobs" so you can see what
 * went wrong and retry them: php simple queue:failed / queue:retry.
 */
final class FailedJobs
{
    public static function record(string $queue, string $payload, Throwable $exception): void
    {
        self::query()->insert([
            'uuid' => Queue::uuid($payload) ?? \Simple\Support\Str::uuid(),
            'queue' => $queue,
            'payload' => $payload,
            'exception' => mb_substr(Queue::describe($exception), 0, 60000),
            'failed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return array<int, array> newest first
     */
    public static function all(): array
    {
        return self::query()->orderBy('id', 'DESC')->get();
    }

    public static function find(int|string $id): ?array
    {
        return self::query()->where(is_numeric($id) ? 'id' : 'uuid', $id)->first();
    }

    /**
     * Push a failed job back onto its queue (with fresh attempts) and remove it from the failed list.
     */
    public static function retry(int|string $id, ?string $connection = null): bool
    {
        $failed = self::find($id);
        if ($failed === null) {
            return false;
        }

        Queue::driver($connection ?? self::workerConnection())->push($failed['queue'], $failed['payload']);
        self::query()->where('id', $failed['id'])->delete();

        return true;
    }

    public static function forget(int|string $id): bool
    {
        $failed = self::find($id);
        return $failed !== null && self::query()->where('id', $failed['id'])->delete() > 0;
    }

    public static function flush(): int
    {
        return self::query()->where('id', '>', 0)->delete();
    }

    private static function workerConnection(): string
    {
        $default = config('queue.default', 'sync');
        return $default === 'sync' ? 'database' : $default;
    }

    private static function query(): QueryBuilder
    {
        return new QueryBuilder(Connection::get(), config('queue.failed.table', 'failed_jobs'));
    }
}
