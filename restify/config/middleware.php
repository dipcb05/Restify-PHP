<?php

declare(strict_types=1);

use Restify\Middleware\AuthenticationMiddleware;
use Restify\Middleware\CorsMiddleware;
use Restify\Middleware\ExceptionMiddleware;
use Restify\Middleware\LoggingMiddleware;
use Restify\Middleware\RateLimitMiddleware;
use Restify\Support\Config;

$exceptions = Config::get('exceptions', []);
$cors = Config::get('cors', []);
$auth = Config::get('auth', []);
$logging = Config::get('logging', []);

return [
    'global' => [
        [
            ExceptionMiddleware::class,
            [$exceptions]
        ],
        [
            CorsMiddleware::class,
            [$cors]
        ],
        [
            RateLimitMiddleware::class,
            [
                'limit' => (int) ($_ENV['RATE_LIMIT_MAX'] ?? 60),
                'seconds' => (int) ($_ENV['RATE_LIMIT_WINDOW'] ?? 60)
            ]
        ],
        [
            AuthenticationMiddleware::class,
            [$auth]
        ],
        [
            LoggingMiddleware::class,
            [$logging]
        ],
    ],
];
