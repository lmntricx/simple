<?php

namespace Simple\Queue;

use InvalidArgumentException;
use RuntimeException;
use Simple\Queue\Drivers\DatabaseDriver;
use Simple\Queue\Drivers\Driver;
use Throwable;

/**
 * Queue::push(new SendInvoice($invoice), delay: 60);   or   SendInvoice::dispatch($invoice);
 *
 * The connection comes from config/queue.php (QUEUE_CONNECTION):
 *   sync     - run immediately, in the request (default; no worker needed)
 *   database - store in the "jobs" table; run `php simple queue:work`
 */
final class Queue
{
    /** @var array<string, Driver> */
    private static array $drivers = [];
    private static ?FakeQueue $fake = null;

    public static function push(Job $job, int $delay = 0, ?string $connection = null): void
    {
        if (self::$fake !== null) {
            self::$fake->record($job, $delay, $connection);
            return;
        }

        $connection ??= config('queue.default', 'sync');

        if ($connection === 'sync') {
            app(Worker::class)->runNow($job);
            return;
        }

        self::driver($connection)->push($job->queue, self::payload($job), $delay);
    }

    public static function driver(?string $connection = null): Driver
    {
        $connection ??= config('queue.default', 'sync');

        if (isset(self::$drivers[$connection])) {
            return self::$drivers[$connection];
        }

        $config = config("queue.connections.$connection");
        if (!is_array($config)) {
            throw new InvalidArgumentException("Queue connection [$connection] is not configured in config/queue.php.");
        }

        return self::$drivers[$connection] = match ($config['driver'] ?? $connection) {
            'database' => new DatabaseDriver(
                $config['table'] ?? 'jobs',
                (int) ($config['retry_after'] ?? 90),
                $config['connection'] ?? null,
            ),
            default => throw new InvalidArgumentException("Unknown queue driver for connection [$connection]."),
        };
    }

    public static function useDriver(string $connection, Driver $driver): void
    {
        self::$drivers[$connection] = $driver;
    }

    public static function fake(): FakeQueue
    {
        return self::$fake = new FakeQueue();
    }

    public static function reset(): void
    {
        self::$fake = null;
        self::$drivers = [];
    }

    /**
     * Serialize a job for storage.
     */
    public static function payload(Job $job): string
    {
        return json_encode([
            'uuid' => \Simple\Support\Str::uuid(),
            'job' => $job::class,
            'queue' => $job->queue,
            'tries' => $job->tries,
            'data' => base64_encode(serialize($job)),
            'pushed_at' => time(),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Rebuild a job from its payload. Models inside are re-fetched from the
     * database, which throws if one has been deleted in the meantime.
     */
    public static function unpack(string $payload): Job
    {
        $data = json_decode($payload, true);
        $job = is_array($data) ? @unserialize(base64_decode($data['data'] ?? '', true) ?: '') : false;

        if (!$job instanceof Job) {
            throw new RuntimeException('Invalid job payload' . (isset($data['job']) ? " for [{$data['job']}]" : '') . '.');
        }

        return $job;
    }

    public static function uuid(string $payload): ?string
    {
        return json_decode($payload, true)['uuid'] ?? null;
    }

    /**
     * Human-readable description of an exception for the failed_jobs table.
     */
    public static function describe(Throwable $e): string
    {
        return sprintf("%s: %s in %s:%d\n%s", $e::class, $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
    }
}
