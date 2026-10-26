<?php

use App\Providers\AppServiceProvider;
use App\Providers\CookiesServiceProvider;
use App\Providers\LabelServiceProvider;
use App\Providers\RateServiceProvider;
use App\Providers\SettingsServiceProvider;
use App\Providers\SocialiteServiceProvider;
use App\Providers\TrackingServiceProvider;

return [
    AppServiceProvider::class,
    SettingsServiceProvider::class,
    CookiesServiceProvider::class,
    SocialiteServiceProvider::class,
    TrackingServiceProvider::class,
    LabelServiceProvider::class,
    RateServiceProvider::class,
];
