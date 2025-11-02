<?php

declare(strict_types=1);

use Restify\Middleware\AuthenticationMiddleware;
use Restify\Middleware\LoggingMiddleware;

return [
    'global' => [
        AuthenticationMiddleware::class,
        LoggingMiddleware::class,
    ],
];

