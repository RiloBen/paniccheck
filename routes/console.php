<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('paniccheck:cleanup-photos')->daily();

// Menyuruh sistem memanggil robot 'events:check-stale' setiap 1 menit
Schedule::command('events:check-stale')->everyMinute();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
