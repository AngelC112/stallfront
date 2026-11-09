<?php

use App\Models\Setting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Su hosting condiviso basta un cron ogni minuto: php artisan schedule:run
Schedule::command('notifications:dispatch')->everyMinute()->withoutOverlapping();
Schedule::command('notifications:send-reminders')->dailyAt((string) Setting::get('notifications.reminder_time', '18:00'));
Schedule::command('housekeeping:run')->dailyAt('03:00');
Schedule::command('backup:database')->dailyAt('02:30')->withoutOverlapping();
Schedule::command('mail:store-sent')->everyMinute()->withoutOverlapping();
