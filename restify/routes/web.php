<?php

declare(strict_types=1);

use Restify\Http\Request;
use Restify\Http\Response;
use Restify\Routing\Router;

return static function (Router $router): void {
    $router->get('/', static function (Request $request): Response {
        return Response::json(
            data: [
                'app' => $_ENV['APP_NAME'] ?? 'Restify',
                'time' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ],
            message: 'Welcome to Restify-PHP.'
        );
    });
};
