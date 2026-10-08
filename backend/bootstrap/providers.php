<?php

use App\Providers\AppServiceProvider;
use App\Providers\LocalizationServiceProvider;
use Laravel\Telescope\TelescopeServiceProvider;
use Stancl\Tenancy\TenancyServiceProvider;

return [
    AppServiceProvider::class,
    TenancyServiceProvider::class,
    App\Providers\TenancyServiceProvider::class,
    TelescopeServiceProvider::class,
    App\Providers\TelescopeServiceProvider::class,
    LocalizationServiceProvider::class,
];
