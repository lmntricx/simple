<?php

namespace Simple\Queue;

use Throwable;

/**
 * A unit of work to run in the background.
 *
 *   class SendInvoice extends Job {
 *       public int $tries = 5;
 *       public function __construct(public Invoice $invoice) {}
 *       public function handle(Mailer $mailer): void { ... }      // dependencies are injected
 *       public function failed(Throwable $e): void { ... }        // optional, after the last attempt
 *   }
 *
 *   SendInvoice::dispatch($invoice);
 *   SendInvoice::dispatch($invoice)->delay(60)->onQueue('emails');
 *
 * Jobs are serialized with PHP's serialize(). Models inside them are stored as
 * class + id and re-fetched when the job runs, so the job sees current data.
 */
abstract class Job
{
    /** Attempts before the job is recorded as failed. */
    public int $tries = 3;

    /** Seconds to wait before retrying; an array gives a delay per attempt, e.g. [10, 60, 300]. */
    public int|array $backoff = 10;

    /** Queue name; workers can process queues in priority order. */
    public string $queue = 'default';

    // public function handle(...): void  — required; parameters are resolved from the container

    /**
     * Called once the job has used up its attempts.
     */
    public function failed(Throwable $exception): void
    {
    }

    /**
     * Create the job and queue it (when the returned object goes out of scope).
     */
    public static function dispatch(mixed ...$arguments): PendingDispatch
    {
        return new PendingDispatch(new static(...$arguments));
    }

    /**
     * Run the job right now, in this process, without the queue.
     */
    public static function dispatchSync(mixed ...$arguments): void
    {
        app()->call([new static(...$arguments), 'handle']);
    }

    /**
     * Seconds to wait before attempt number $attempt + 1.
     */
    public function backoffFor(int $attempt): int
    {
        if (is_int($this->backoff)) {
            return $this->backoff;
        }

        return $this->backoff === [] ? 0 : (int) ($this->backoff[$attempt - 1] ?? end($this->backoff));
    }
}
