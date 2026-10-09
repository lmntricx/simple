<?php

namespace Simple\Console;

use DateTimeImmutable;
use Simple\Scheduling\Schedule;

/**
 * schedule:run, schedule:list, schedule:work
 */
trait ScheduleCommands
{
    private ?Schedule $schedule = null;

    /**
     * The schedule from routes/schedule.php.
     */
    public function schedule(): Schedule
    {
        if ($this->schedule === null) {
            $this->schedule = new Schedule($this->app, $this->out);
            $file = $this->app->basePath . '/routes/schedule.php';
            if (is_file($file)) {
                (require $file)($this->schedule);
            }
        }

        return $this->schedule;
    }

    private function scheduleRun(): int
    {
        $results = $this->schedule()->runDue(new DateTimeImmutable(), fn (string $line) => $this->line(date('Y-m-d H:i:s') . '  ' . $line));

        if ($results === []) {
            $this->line('No scheduled tasks are due.');
        }

        return in_array('failed', array_column($results, 'status'), true) ? 1 : 0;
    }

    private function scheduleList(): int
    {
        $events = $this->schedule()->events();

        if ($events === []) {
            $this->line('No scheduled tasks. Define them in routes/schedule.php.');
            return 0;
        }

        $now = new DateTimeImmutable();
        foreach ($events as $event) {
            $this->line(sprintf(
                '  %-16s %-40s next: %s%s',
                $event->getExpression(),
                $event->getDescription(),
                $event->nextRunDate($now)->format('Y-m-d H:i'),
                $event->preventsOverlapping() ? '  (no overlap)' : '',
            ));
        }

        return 0;
    }

    /**
     * Development helper: run the schedule every minute in the foreground.
     */
    private function scheduleWork(): int
    {
        $this->line('Running scheduled tasks every minute. Press Ctrl+C to stop.');

        while (true) {
            sleep(60 - (int) date('s'));
            $this->scheduleRun();
        }
    }
}
