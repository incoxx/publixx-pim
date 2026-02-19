<?php

declare(strict_types=1);

/**
 * Publixx PIM – Sanctum-Konfiguration
 *
 * Ergänzt/überschreibt die Default-Sanctum-Config.
 */

return [
    'stateful' => explode(',', env(
        'SANCTUM_STATEFUL_DOMAINS',
        sprintf(
            '%s%s%s',
            'localhost,localhost:3000,localhost:5173,127.0.0.1,127.0.0.1:8000,::1',
            env('APP_URL') ? ',' . parse_url(env('APP_URL'), PHP_URL_HOST) : '',
            env('FRONTEND_URL') ? ',' . parse_url(env('FRONTEND_URL'), PHP_URL_HOST) : '',
        )
    )),

    'guard' => ['web'],

    // Token-Ablaufzeit in Minuten (24h = 1440 min)
    'expiration' => (int) env('SANCTUM_TOKEN_EXPIRATION_MINUTES', 1440),

    // Custom: Ablaufzeit in Stunden (für AuthController)
    'expiration_hours' => (int) env('SANCTUM_TOKEN_EXPIRATION_HOURS', 24),

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],
];
