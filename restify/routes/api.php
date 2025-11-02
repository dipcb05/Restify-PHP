<?php

declare(strict_types=1);

use Restify\Routing\Router;

return static function (Router $router): void {
    $router->get(
        '/health',
        static fn (): array => [
            'status' => 'ok',
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ],
        [
            'summary' => 'Health check endpoint',
            'description' => 'Returns a basic readiness payload for infrastructure monitors.',
            'tags' => ['System'],
            'responses' => [
                '200' => [
                    'description' => 'Service is ready to accept traffic.',
                    'example' => [
                        'status' => 'ok',
                        'timestamp' => '2025-01-01T00:00:00+00:00',
                    ],
                ],
            ],
        ]
    );
};
