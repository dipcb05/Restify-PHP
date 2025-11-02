<?php

declare(strict_types=1);

use Restify\Middleware\AuthenticationMiddleware;
use Restify\Middleware\LoggingMiddleware;
use Restify\Middleware\RateLimitMiddleware;

return [
    'global' => [
        [
            RateLimitMiddleware::class,
            [
                'limit' => (int) ($_ENV['RATE_LIMIT_MAX'] ?? 60),
                'seconds' => (int) ($_ENV['RATE_LIMIT_WINDOW'] ?? 60)
            ]
        ],
        AuthenticationMiddleware::class,
        LoggingMiddleware::class,
    ],
];
