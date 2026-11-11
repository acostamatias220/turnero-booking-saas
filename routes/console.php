<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('appointments:expire-holds')->everyMinute()->withoutOverlapping();
Schedule::command('appointments:send-reminders')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('holidays:import')->yearlyOn(12, 1, '03:00');
Schedule::command('holidays:import', [now()->addYear()->year])->yearlyOn(12, 1, '03:05');
