<?php

namespace Simple\Tests\Feature\Queue;

use Simple\Tests\Fixtures\Models\AuthUser as User;
use RuntimeException;
use Simple\Application;
use Simple\Console\Kernel;
use Simple\Database\Connection;
use Simple\Database\QueryBuilder;
use Simple\Queue\FailedJobs;
use Simple\Queue\Job;
use Simple\Queue\Queue;
use Simple\Queue\Worker;
use Simple\Tests\TestCase;
use Throwable;

final class JobLog
{
    public static array $entries = [];
    public static int $flakyCalls = 0;
}

class LogJob extends Job
{
    public function __construct(public string $message)
    {
    }

    public function handle(): void
    {
        JobLog::$entries[] = $this->message;
    }
}

class FailingJob extends Job
{
    public int $tries = 2;
    public int|array $backoff = 0;

    public function handle(): void
    {
        throw new RuntimeException('boom');
    }

    public function failed(Throwable $exception): void
    {
        JobLog::$entries[] = 'failed: ' . $exception->getMessage();
    }
}

class FlakyJob extends Job
{
    public int $tries = 3;
    public int|array $backoff = [0, 30];

    public function handle(): void
    {
        if (++JobLog::$flakyCalls === 1) {
            throw new RuntimeException('first attempt fails');
        }
        JobLog::$entries[] = 'flaky done';
    }
}

class UserJob extends Job
{
    public function __construct(public readonly User $user)
    {
    }

    public function handle(): void
    {
        JobLog::$entries[] = 'user: ' . $this->user->name;
    }
}

class InjectedJob extends Job
{
    public function handle(Application $app): void
    {
        JobLog::$entries[] = 'injected: ' . $app::class;
    }
}

class QueueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        JobLog::$entries = [];
        JobLog::$flakyCalls = 0;
    }

    private function useDatabaseQueue(): void
    {
        $this->app->config->set('queue.default', 'database');
    }

    private function jobs(): QueryBuilder
    {
        return new QueryBuilder(Connection::get(), 'jobs');
    }

    /** Process everything currently available. */
    private function work(array $queues = ['default']): int
    {
        return $this->app->make(Worker::class)->work(Queue::driver('database'), $queues, ['stop_when_empty' => true]);
    }

    private function processNext(): string
    {
        $driver = Queue::driver('database');
        $job = $driver->pop(['default']);
        $this->assertNotNull($job, 'expected a job to be available');

        return $this->app->make(Worker::class)->process($driver, $job);
    }

    // ---- sync --------------------------------------------------------------

    public function test_sync_connection_runs_jobs_immediately(): void
    {
        LogJob::dispatch('hello');
        $this->assertSame(['hello'], JobLog::$entries);
    }

    public function test_sync_failures_reach_the_caller_after_the_failed_hook(): void
    {
        try {
            FailingJob::dispatch();
            $this->fail('expected exception');
        } catch (RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }
        $this->assertSame(['failed: boom'], JobLog::$entries);
    }

    // ---- database ----------------------------------------------------------

    public function test_jobs_are_stored_and_processed_by_the_worker(): void
    {
        $this->useDatabaseQueue();
        LogJob::dispatch('queued');

        $this->assertSame([], JobLog::$entries, 'not run yet');
        $this->assertSame(1, Queue::driver('database')->size('default'));

        $this->assertSame(1, $this->work());
        $this->assertSame(['queued'], JobLog::$entries);
        $this->assertSame(0, $this->jobs()->count(), 'deleted after success');
    }

    public function test_delayed_jobs_wait(): void
    {
        $this->useDatabaseQueue();
        LogJob::dispatch('later')->delay(60);

        $this->assertNull(Queue::driver('database')->pop(['default']));

        $this->jobs()->where('id', '>', 0)->update(['available_at' => time() - 1]);
        $this->assertSame(1, $this->work());
        $this->assertSame(['later'], JobLog::$entries);
    }

    public function test_queues_are_processed_in_priority_order(): void
    {
        $this->useDatabaseQueue();
        LogJob::dispatch('low');
        LogJob::dispatch('urgent')->onQueue('high');

        $this->work(['high', 'default']);

        $this->assertSame(['urgent', 'low'], JobLog::$entries);
    }

    public function test_failed_jobs_are_retried_then_recorded(): void
    {
        $this->useDatabaseQueue();
        FailingJob::dispatch();

        $this->assertSame('released', $this->processNext());
        $this->assertSame(1, $this->jobs()->first()['attempts']);
        $this->assertSame([], JobLog::$entries, 'failed() not called before the last attempt');

        $this->assertSame('failed', $this->processNext());
        $this->assertSame(0, $this->jobs()->count());
        $this->assertSame(['failed: boom'], JobLog::$entries);

        $failed = FailedJobs::all();
        $this->assertCount(1, $failed);
        $this->assertStringContainsString('RuntimeException: boom', $failed[0]['exception']);
    }

    public function test_backoff_delays_the_retry_and_the_job_can_then_succeed(): void
    {
        $this->useDatabaseQueue();
        FlakyJob::dispatch();

        $this->assertSame('released', $this->processNext());
        $this->assertSame('processed', $this->processNext(), 'first backoff is 0 seconds');
        $this->assertSame(['flaky done'], JobLog::$entries);

        $this->assertSame(30, (new FlakyJob())->backoffFor(2));
        $this->assertSame(30, (new FlakyJob())->backoffFor(9), 'last value repeats');
    }

    public function test_models_are_stored_by_id_and_refetched(): void
    {
        $this->useDatabaseQueue();
        $user = User::make(name: 'Original Name');

        UserJob::dispatch($user);

        $payload = $this->jobs()->first()['payload'];
        $this->assertStringNotContainsString('Original Name', base64_decode(json_decode($payload, true)['data']));

        $user->update(['name' => 'Renamed']);
        $this->work();

        $this->assertSame(['user: Renamed'], JobLog::$entries, 'job sees current data');
    }

    public function test_job_fails_immediately_if_its_model_was_deleted(): void
    {
        $this->useDatabaseQueue();
        $user = User::make();
        UserJob::dispatch($user);
        $user->delete();

        $this->assertSame('failed', $this->processNext());
        $this->assertStringContainsString('no longer exists', FailedJobs::all()[0]['exception']);
    }

    public function test_dependencies_are_injected_into_handle(): void
    {
        $this->useDatabaseQueue();
        InjectedJob::dispatch();
        $this->work();

        $this->assertSame(['injected: ' . Application::class], JobLog::$entries);
    }

    public function test_a_job_can_only_be_claimed_once(): void
    {
        $this->useDatabaseQueue();
        LogJob::dispatch('once');
        $driver = Queue::driver('database');

        $this->assertNotNull($driver->pop(['default']));
        $this->assertNull($driver->pop(['default']), 'second worker gets nothing');
    }

    public function test_abandoned_jobs_are_handed_out_again(): void
    {
        $this->useDatabaseQueue();
        LogJob::dispatch('crash');
        $driver = Queue::driver('database');
        $driver->pop(['default']);

        // The worker died; after retry_after (90 s) the job is available again.
        $this->jobs()->where('id', '>', 0)->update(['reserved_at' => time() - 91]);

        $job = $driver->pop(['default']);
        $this->assertNotNull($job);
        $this->assertSame(2, $job->attempts);
    }

    public function test_corrupt_payload_is_recorded_as_failed(): void
    {
        $this->useDatabaseQueue();
        Queue::driver('database')->push('default', '{"job":"Nope","data":"garbage"}');

        $this->assertSame('failed', $this->processNext());
        $this->assertCount(1, FailedJobs::all());
    }

    // ---- commands ----------------------------------------------------------

    private function command(string ...$args): array
    {
        $out = fopen('php://memory', 'w+');
        $code = (new Kernel($this->app, $out))->handle(['simple', ...$args]);
        rewind($out);
        return [$code, stream_get_contents($out)];
    }

    public function test_queue_work_once(): void
    {
        $this->useDatabaseQueue();
        LogJob::dispatch('a');
        LogJob::dispatch('b');

        [$code, $output] = $this->command('queue:work', '--once');

        $this->assertSame(0, $code);
        $this->assertSame(['a'], JobLog::$entries);
        $this->assertStringContainsString('Processed', $output);
    }

    public function test_queue_work_refuses_on_sync(): void
    {
        [$code, $output] = $this->command('queue:work');
        $this->assertSame(1, $code);
        $this->assertStringContainsString('sync', $output);
    }

    public function test_failed_retry_forget_flush(): void
    {
        $this->useDatabaseQueue();
        FailingJob::dispatch();
        $this->processNext();
        $this->processNext();

        [, $list] = $this->command('queue:failed');
        $this->assertStringContainsString(FailingJob::class, $list);

        $id = (string) FailedJobs::all()[0]['id'];
        $this->command('queue:retry', $id);
        $this->assertSame([], FailedJobs::all());
        $this->assertSame(1, $this->jobs()->count(), 'back on the queue');
        $this->assertSame(0, $this->jobs()->first()['attempts'], 'with fresh attempts');

        $this->processNext();
        $this->processNext();
        $this->command('queue:forget', (string) FailedJobs::all()[0]['id']);
        $this->assertSame([], FailedJobs::all());

        FailingJob::dispatch();
        $this->processNext();
        $this->processNext();
        [, $out] = $this->command('queue:flush');
        $this->assertStringContainsString('Deleted 1', $out);
    }

    // ---- fake ----------------------------------------------------------------

    public function test_queue_fake_records_jobs(): void
    {
        $queue = Queue::fake();

        LogJob::dispatch('x')->delay(5);
        LogJob::dispatch('y')->onQueue('emails');

        $this->assertCount(2, $queue->pushed(LogJob::class));
        $this->assertCount(1, $queue->pushed(LogJob::class, fn (LogJob $j) => $j->queue === 'emails'));
        $this->assertSame([], JobLog::$entries, 'not run');

        $queue->runAll();
        $this->assertSame(['x', 'y'], JobLog::$entries);
    }
}
