<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('feeds:collect')->everyFiveMinutes()
    ->after(function () {
        try {
            Artisan::call('topics:rebuild');
            Log::info('topics:rebuild completed successfully');

            Artisan::call('clusters:rebuild');
            Log::info('clusters:rebuild completed successfully');

            Artisan::call('trends:rebuild');
            Log::info('trends:rebuild completed successfully');

            Artisan::call('trends:snapshot');
            Log::info('All tasks completed successfully');

        } catch (Exception $e) {
            Log::error('Task failed: '.$e->getMessage());
        }
    });
