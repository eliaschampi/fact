<?php

use App\Models\AuthAccount;

return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'auth_accounts',
        ],
    ],

    'providers' => [
        'auth_accounts' => [
            'driver' => 'eloquent',
            'model' => AuthAccount::class,
        ],
    ],

];
