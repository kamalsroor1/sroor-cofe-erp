<?php

use App\Providers\AppServiceProvider;
use App\Providers\CentralFortifyServiceProvider;
use App\Providers\HealthServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\LocalizationServiceProvider;
use Laravel\Telescope\TelescopeServiceProvider;
use Stancl\Tenancy\TenancyServiceProvider;

return [
    AppServiceProvider::class,
    TenancyServiceProvider::class,
    App\Providers\TenancyServiceProvider::class,
    TelescopeServiceProvider::class,
    App\Providers\TelescopeServiceProvider::class,
    HorizonServiceProvider::class,
    LocalizationServiceProvider::class,
    // IDEN-1.12: headless Fortify for platform operators (Fortify itself is not auto-discovered).
    CentralFortifyServiceProvider::class,
    // OPS-5 / OPS-7: health checks + the Google Drive backup disk driver.
    HealthServiceProvider::class,
];
