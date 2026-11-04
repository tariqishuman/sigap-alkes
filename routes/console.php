<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Status first, so reminders are sent against up-to-date equipment.
Schedule::command('equipment:evaluate-status')->dailyAt('00:10');
Schedule::command('calibration:send-reminders')->dailyAt('07:00');
