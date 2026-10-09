<?php

namespace Simple\Queue;

use Closure;
use Simple\Application;
use Simple\Auth\Auth;
use Simple\Queue\Drivers\Driver;
use Simple\Support\Log;
use Throwable;

/**
 * Runs queued jobs: php simple queue:work
 */
class Worker
{
    private bool $shouldStop = false;

    public function __construct(private Application $app)
    {
    }

    /**
     * Process jobs until told to stop.
     *
     * Options: sleep (seconds to wait when the queue is empty, default 3), once,
     * stop_when_empty, max_jobs, max_time (seconds), memory (MB, default 128).
     *
     * @param string[] $queues in priority order
     * @param Closure(string): void|null $output receives a line per job
     * @return int jobs processed
     */
    public function work(Driver $driver, array $queues, array $options = [], ?Closure $output = null): int
    {
        $start = time();
        $processed = 0;
        $this->listenForSignals();

        while (!$this->shouldStop) {
            $job = $driver->pop($queues);

            if ($job === null) {
                if (($options['once'] ?? false) || ($options['stop_when_empty'] ?? false)) {
                    break;
                }
                sleep(max(1, (int) ($options['sleep'] ?? 3)));
            } else {
                $this->process($driver, $job, $output);
                $processed++;

                if ($options['once'] ?? false) {
                    break;
                }
            }

            if (($max = (int) ($options['max_jobs'] ?? 0)) > 0 && $processed >= $max) {
                break;
            }
            if (($max = (int) ($options['max_time'] ?? 0)) > 0 && time() - $start >= $max) {
                break;
            }
            // Restart before PHP's memory creeps too high; run the worker under a process manager.
            if (memory_get_usage(true) / 1048576 >= (int) ($options['memory'] ?? 128)) {
                $output && $output('Memory limit reached; stopping.');
                break;
            }
        }

        return $processed;
    }

    /**
     * Run one reserved job: done => deleted; error => released for a retry, or
     * recorded in failed_jobs after the last attempt.
     *
     * @return string "processed", "released" or "failed"
     */
    public function process(Driver $driver, ReservedJob $reserved, ?Closure $output = null): string
    {
        Auth::forget();
        $started = hrtime(true);
        $job = null;

        try {
            $job = Queue::unpack($reserved->payload);
            $this->app->call([$job, 'handle']);
            $driver->delete($reserved);

            $output && $output(sprintf('Processed  %s (%.0f ms)', $reserved->name(), (hrtime(true) - $started) / 1e6));
            return 'processed';
        } catch (Throwable $e) {
            Log::exception($e);

            // A job that can't even be unserialized (e.g. its model was deleted) won't succeed later either.
            $tries = $job?->tries ?? 1;

            if ($reserved->attempts < $tries) {
                $delay = $job->backoffFor($reserved->attempts);
                $driver->release($reserved, $delay);
                $output && $output(sprintf('Released   %s (attempt %d of %d, retry in %ds): %s', $reserved->name(), $reserved->attempts, $tries, $delay, $e->getMessage()));
                return 'released';
            }

            $this->fail($driver, $reserved, $job, $e);
            $output && $output(sprintf('Failed     %s: %s', $reserved->name(), $e->getMessage()));
            return 'failed';
        }
    }

    /**
     * The "sync" connection: run immediately. Exceptions reach the caller,
     * after the job's failed() hook has run.
     */
    public function runNow(Job $job): void
    {
        try {
            $this->app->call([$job, 'handle']);
        } catch (Throwable $e) {
            $this->callFailedHook($job, $e);
            throw $e;
        }
    }

    public function stop(): void
    {
        $this->shouldStop = true;
    }

    private function fail(Driver $driver, ReservedJob $reserved, ?Job $job, Throwable $e): void
    {
        FailedJobs::record($reserved->queue, $reserved->payload, $e);
        $driver->delete($reserved);

        if ($job !== null) {
            $this->callFailedHook($job, $e);
        }
    }

    private function callFailedHook(Job $job, Throwable $e): void
    {
        try {
            $job->failed($e);
        } catch (Throwable $hookError) {
            Log::exception($hookError);
        }
    }

    /**
     * Finish the current job, then stop, on Ctrl+C / SIGTERM (Linux/macOS with pcntl).
     */
    private function listenForSignals(): void
    {
        if (!function_exists('pcntl_async_signals')) {
            return;
        }

        pcntl_async_signals(true);
        foreach ([SIGTERM, SIGINT, SIGQUIT] as $signal) {
            pcntl_signal($signal, fn () => $this->stop());
        }
    }
}
