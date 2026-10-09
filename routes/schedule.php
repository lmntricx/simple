<?php

use Simple\Scheduling\Schedule;

/*
 * Scheduled tasks. Run them with one cron entry, every minute:
 *
 *   * * * * * cd /path/to/project && php simple schedule:run >> /dev/null 2>&1
 *
 * (Windows: a Task Scheduler task running `php simple schedule:run` every minute.)
 * See them with `php simple schedule:list`; in development, `php simple schedule:work`.
 */
return function (Schedule $schedule) {
    // Remove expired API tokens and password reset tokens.
    $schedule->command('auth:prune')->daily();

    // $schedule->job(new \App\Jobs\SendWeeklyReport())->weeklyOn(1, '08:00');
    // $schedule->call(fn () => ...)->everyFifteenMinutes()->withoutOverlapping();
};
