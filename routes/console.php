<?php

use Illuminate\Support\Facades\Schedule;

/*
| Console commands & scheduled tasks.
| On Hostinger shared hosting, a single cron entry drives the scheduler:
|   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
| Register domain schedules here as the app grows, e.g.:
|   Schedule::command('definance:period-close')->monthlyOn(1, '02:00');
*/

// Keep the queue drained on shared hosting without a long-running worker.
Schedule::command('queue:work --stop-when-empty --tries=3')
    ->everyMinute()
    ->withoutOverlapping();
