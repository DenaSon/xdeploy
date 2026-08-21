<?php

use App\Application\Cloud\Jobs\DispatchExpiredCloudServerTerminationsJob;
use App\Application\Cloud\Jobs\DispatchExpiringCloudServerNotificationsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(
    new DispatchExpiredCloudServerTerminationsJob,
)
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::job(
    new DispatchExpiringCloudServerNotificationsJob,
)
    ->everyFifteenMinutes()
    ->withoutOverlapping();

Schedule::command('cloud:catalog:warm')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->onOneServer();
