<?php

namespace Simple\Tests\Feature\Scheduling;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Simple\Auth\PersonalAccessToken;
use Simple\Console\Kernel;
use Simple\Queue\Job;
use Simple\Queue\Queue;
use Simple\Scheduling\Event;
use Simple\Scheduling\Schedule;
use Simple\Tests\TestCase;

class ScheduledJob extends Job
{
    public function handle(): void
    {
    }
}

class ScheduleTest extends TestCase
{
    private Schedule $schedule;
    private array $ran = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->schedule = new Schedule($this->app, fopen('php://memory', 'w+'));
    }

    /** "task" => "status" (fine when task names are unique) */
    private function statuses(array $results): array
    {
        return array_column($results, 'status', 'task');
    }

    private function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable($time);
    }

    private function track(string $name): \Closure
    {
        return function () use ($name) {
            $this->ran[] = $name;
        };
    }

    public static function frequencies(): array
    {
        return [
            'everyMinute' => [fn (Event $e) => $e->everyMinute(), '* * * * *'],
            'everyFiveMinutes' => [fn (Event $e) => $e->everyFiveMinutes(), '*/5 * * * *'],
            'everyThirtyMinutes' => [fn (Event $e) => $e->everyThirtyMinutes(), '0,30 * * * *'],
            'hourly' => [fn (Event $e) => $e->hourly(), '0 * * * *'],
            'hourlyAt' => [fn (Event $e) => $e->hourlyAt(17), '17 * * * *'],
            'daily' => [fn (Event $e) => $e->daily(), '0 0 * * *'],
            'dailyAt' => [fn (Event $e) => $e->dailyAt('13:05'), '5 13 * * *'],
            'twiceDaily' => [fn (Event $e) => $e->twiceDaily(1, 13), '0 1,13 * * *'],
            'weekly' => [fn (Event $e) => $e->weekly(), '0 0 * * 0'],
            'weeklyOn' => [fn (Event $e) => $e->weeklyOn(1, '08:00'), '0 8 * * 1'],
            'monthly' => [fn (Event $e) => $e->monthly(), '0 0 1 * *'],
            'monthlyOn' => [fn (Event $e) => $e->monthlyOn(15, '12:30'), '30 12 15 * *'],
            'quarterly' => [fn (Event $e) => $e->quarterly(), '0 0 1 1,4,7,10 *'],
            'yearly' => [fn (Event $e) => $e->yearly(), '0 0 1 1 *'],
            'dailyAt + weekdays' => [fn (Event $e) => $e->dailyAt('09:00')->weekdays(), '0 9 * * 1-5'],
            'cron shortcut + weekends' => [fn (Event $e) => $e->cron('@daily')->weekends(), '0 0 * * 0,6'],
        ];
    }

    #[DataProvider('frequencies')]
    public function test_frequency_helpers(\Closure $configure, string $expected): void
    {
        $event = $configure($this->schedule->call(fn () => null));
        $this->assertSame($expected, $event->getExpression());
    }

    public function test_only_due_events_run(): void
    {
        $this->schedule->call($this->track('every-minute'))->everyMinute();
        $this->schedule->call($this->track('hourly'))->hourly();
        $this->schedule->call($this->track('daily-at-2'))->dailyAt('02:00');

        $this->schedule->runDue($this->at('2026-10-12 10:00'));
        $this->assertSame(['every-minute', 'hourly'], $this->ran);

        $this->ran = [];
        $this->schedule->runDue($this->at('2026-10-12 02:00'));
        $this->assertSame(['every-minute', 'hourly', 'daily-at-2'], $this->ran);
    }

    public function test_when_skip_and_environments(): void
    {
        $this->schedule->call($this->track('when-false'))->when(fn () => false);
        $this->schedule->call($this->track('skip-true'))->skip(fn () => true);
        $this->schedule->call($this->track('production-only'))->environments('production');
        $this->schedule->call($this->track('testing'))->environments('testing', 'local');

        $results = $this->schedule->runDue($this->at('2026-10-12 10:00'));

        $this->assertSame(['testing'], $this->ran);
        $this->assertSame(['skipped', 'skipped', 'ran'], array_column($results, 'status'), 'production-only is not due at all in testing');
    }

    public function test_a_failing_task_does_not_stop_the_others(): void
    {
        $this->schedule->call(fn () => throw new RuntimeException('nope'), 'broken');
        $this->schedule->call($this->track('after'), 'after');

        $results = $this->schedule->runDue($this->at('2026-10-12 10:00'));

        $this->assertSame(['broken' => 'failed', 'after' => 'ran'], $this->statuses($results));
        $this->assertSame(['after'], $this->ran);
    }

    public function test_without_overlapping_skips_while_the_previous_run_holds_the_lock(): void
    {
        $event = $this->schedule->call($this->track('locked'), 'locked task')->withoutOverlapping();

        // Simulate a previous schedule:run still executing this task.
        $dir = storage_path('framework/schedule');
        @mkdir($dir, 0775, true);
        $lock = fopen($dir . '/' . $event->mutexName() . '.lock', 'c');
        $this->assertTrue(flock($lock, LOCK_EX | LOCK_NB));

        try {
            $results = $this->schedule->runDue($this->at('2026-10-12 10:00'));
            $this->assertSame(['locked task' => 'running'], $this->statuses($results));
            $this->assertSame([], $this->ran);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        $this->schedule->runDue($this->at('2026-10-12 10:01'));
        $this->assertSame(['locked'], $this->ran, 'runs once the lock is free');
    }

    public function test_closure_tasks_get_dependencies_injected(): void
    {
        $this->schedule->call(function (\Simple\Application $app) {
            $this->ran[] = $app::class;
        });

        $this->schedule->runDue($this->at('2026-10-12 10:00'));
        $this->assertSame([\Simple\Application::class], $this->ran);
    }

    public function test_command_tasks_run_console_commands(): void
    {
        $user = \Simple\Tests\Fixtures\Models\AuthUser::make();
        $user->createToken('old', expiresAt: new \DateTime('-1 day'));

        $this->schedule->command('auth:prune')->daily();
        $results = $this->schedule->runDue($this->at('2026-10-12 00:00'));

        $this->assertSame(['simple auth:prune' => 'ran'], $this->statuses($results));
        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_failing_commands_are_reported_as_failed(): void
    {
        $this->schedule->command('no:such-command');
        $results = $this->schedule->runDue($this->at('2026-10-12 10:00'));

        $this->assertSame(['simple no:such-command' => 'failed'], $this->statuses($results));
    }

    public function test_job_tasks_push_onto_the_queue(): void
    {
        $queue = Queue::fake();
        $this->schedule->job(new ScheduledJob())->hourly();

        $this->schedule->runDue($this->at('2026-10-12 10:00'));
        $this->schedule->runDue($this->at('2026-10-12 11:00'));

        $this->assertCount(2, $queue->pushed(ScheduledJob::class));
    }

    public function test_schedule_list_and_run_commands_use_routes_schedule_php(): void
    {
        $out = fopen('php://memory', 'w+');
        $kernel = new Kernel($this->app, $out);

        $this->assertSame(0, $kernel->handle(['simple', 'schedule:list']));
        rewind($out);
        $list = stream_get_contents($out);

        $this->assertStringContainsString('0 0 * * *', $list);
        $this->assertStringContainsString('simple auth:prune', $list);
        $this->assertStringContainsString('next: ', $list);
    }

    public function test_invalid_time_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->schedule->call(fn () => null)->dailyAt('25:00');
    }
}
