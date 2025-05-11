<?php

use Laravel\Sanctum\Sanctum;

return [
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        Sanctum::currentApplicationUrlWithPort()
    ))),

    'guard' => ['web'],
    
    // Set expiration ke 60 menit (1 jam)
    'expiration' => 60,
    
    // Token refresh akan berlaku 7 hari (diatur dalam env)
    'refresh_expiration' => env('SANCTUM_REFRESH_EXPIRATION', 60 * 24 * 7),

    'middleware' => [
        'verify_csrf_token' => App\Http\Middleware\VerifyCsrfToken::class,
        'encrypt_cookies' => App\Http\Middleware\EncryptCookies::class,
    ],
];