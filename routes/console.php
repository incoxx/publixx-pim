<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('queue:prune-batches --hours=48')->daily();
Schedule::command('versions:activate-scheduled')->everyMinute();
Schedule::command('pim:export-job --run-scheduled')->everyMinute();
Schedule::command('tms:ingest')->daily();
Schedule::command('tms:sync')->dailyAt('04:00');
