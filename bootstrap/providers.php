<?php

use App\Providers\ApplicationServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\PlatformServiceProvider;
use App\Providers\ServerServiceProvider;
use App\Providers\SmsServiceProvider;
use App\Providers\UserServiceProvider;

return [
    AppServiceProvider::class,
    ServerServiceProvider::class,
    ApplicationServiceProvider::class,
    PlatformServiceProvider::class,
    SmsServiceProvider::class,
    UserServiceProvider::class,
];
