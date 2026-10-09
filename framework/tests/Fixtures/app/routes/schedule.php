<?php

use Simple\Scheduling\Schedule;

return function (Schedule $schedule) {
    $schedule->command('auth:prune')->daily();
};
