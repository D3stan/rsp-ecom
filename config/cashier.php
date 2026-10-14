<?php

/*
|--------------------------------------------------------------------------
| Cashier overrides
|--------------------------------------------------------------------------
|
| Only the keys listed here replace the package defaults (Laravel merges
| this file over vendor/laravel/cashier/config/cashier.php). The shop sells
| in euro, so the default currency is EUR even when CASHIER_CURRENCY is not
| set; the package default would otherwise be USD.
|
*/

return [

    'currency' => env('CASHIER_CURRENCY', 'eur'),

    'currency_locale' => env('CASHIER_CURRENCY_LOCALE', 'it'),

];
