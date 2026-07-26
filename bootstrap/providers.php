<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\ServerServiceProvider;
use App\Providers\UserServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    ServerServiceProvider::class,
    UserServiceProvider::class,
];
