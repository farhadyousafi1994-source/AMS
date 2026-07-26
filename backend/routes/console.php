<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

// Daily automatic database backup (kept to the newest 14 copies).
Schedule::call(fn () => \App\Http\Controllers\BackupController::run())->dailyAt('01:00');
