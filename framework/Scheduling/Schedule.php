<?php

namespace Simple\Scheduling;

use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use Simple\Application;
use Simple\Console\Kernel;
use Simple\Queue\Job;
use Simple\Queue\Queue;
use Simple\Support\Log;
use Throwable;

/**
 * Tasks are defined in routes/schedule.php:
 *
 *   $schedule->command('auth:prune')->daily();
 *   $schedule->job(new SendWeeklyDigest())->weeklyOn(1, '08:00');
 *   $schedule->call(fn (Mailer $m) => ...)->everyFifteenMinutes()->withoutOverlapping();
 *
 * and run by one cron entry (every minute):
 *   * * * * * cd /path/to/project && php simple schedule:run >> /dev/null 2>&1
 */
class Schedule
{
    /** @var Event[] */
    private array $events = [];

    /**
     * @param resource|null $output where scheduled commands write their output (default STDOUT)
     */
    public function __construct(private Application $app, private mixed $output = null)
    {
    }

    /**
     * Run a `php simple ...` command, e.g. ->command('auth:prune') or ->command('db:seed', ['--class=Demo']).
     */
    public function command(string $command, array $arguments = []): Event
    {
        $description = trim('simple ' . $command . ' ' . implode(' ', $arguments));

        return $this->events[] = new Event(function () use ($command, $arguments) {
            $code = (new Kernel($this->app, $this->output))->handle(['simple', $command, ...$arguments]);
            if ($code !== 0) {
                throw new \RuntimeException("Command [$command] exited with code $code.");
            }
            return $code;
        }, $description);
    }

    /**
     * Run a closure; its parameters are injected by the container.
     */
    public function call(Closure $callback, ?string $description = null): Event
    {
        return $this->events[] = new Event(fn () => $this->app->call($callback), $description ?? 'Closure');
    }

    /**
     * Push a job onto the queue (it then runs in the queue worker).
     */
    public function job(Job $job, ?string $connection = null): Event
    {
        return $this->events[] = new Event(
            fn () => Queue::push(clone $job, 0, $connection),
            'job ' . $job::class,
        );
    }

    /** @return Event[] */
    public function events(): array
    {
        return $this->events;
    }

    /** @return Event[] */
    public function dueEvents(DateTimeInterface $now): array
    {
        return array_values(array_filter($this->events, fn (Event $e) => $e->isDue($now)));
    }

    /**
     * Run every event due at $now (one failing event doesn't stop the others).
     *
     * @param Closure(string): void|null $output
     * @return array<int, array{task: string, status: string}> status: ran | skipped | running | failed
     */
    public function runDue(?DateTimeInterface $now = null, ?Closure $output = null): array
    {
        $now ??= new DateTimeImmutable();
        $results = [];

        foreach ($this->dueEvents($now) as $event) {
            $name = $event->getDescription();

            if (!$event->filtersPass()) {
                $results[] = ['task' => $name, 'status' => 'skipped'];
                continue;
            }

            $lock = $event->preventsOverlapping() ? $this->acquireLock($event) : null;
            if ($lock === false) {
                $results[] = ['task' => $name, 'status' => 'running'];
                $output && $output("Skipping   $name (still running)");
                continue;
            }

            $started = hrtime(true);
            try {
                $output && $output("Running    $name");
                $event->run();
                $results[] = ['task' => $name, 'status' => 'ran'];
                $output && $output(sprintf('Done       %s (%.0f ms)', $name, (hrtime(true) - $started) / 1e6));
            } catch (Throwable $e) {
                Log::exception($e);
                $results[] = ['task' => $name, 'status' => 'failed'];
                $output && $output("Failed     $name: " . $e->getMessage());
            } finally {
                if (is_resource($lock)) {
                    flock($lock, LOCK_UN);
                    fclose($lock);
                }
            }
        }

        return $results;
    }

    /**
     * An exclusive, non-blocking file lock. The OS releases it if the process dies,
     * so a crash never leaves a task locked forever.
     *
     * @return resource|false
     */
    private function acquireLock(Event $event): mixed
    {
        $dir = storage_path('framework/schedule');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $handle = fopen($dir . '/' . $event->mutexName() . '.lock', 'c');
        if ($handle === false) {
            return false;
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return false;
        }

        return $handle;
    }
}
