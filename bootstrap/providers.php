<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\ServerServiceProvider;

return [
    AppServiceProvider::class,
    ServerServiceProvider::class,
    FortifyServiceProvider::class,
];
