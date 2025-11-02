<?php

declare(strict_types=1);

namespace Restify\Testing;

use Restify\Core\Application;
use Restify\Http\Request;
use Restify\Http\Response;

abstract class TestCase
{
    protected Application $app;

    public function setUp(): void
    {
    }

    public function tearDown(): void
    {
    }

    public function boot(): void
    {
        $this->setUp();
        $this->app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
    }

    public function shutdown(): void
    {
        $this->tearDown();
    }

    public function call(string $method, string $uri, array $payload = []): Response
    {
        $request = new Request(
            method: strtoupper($method),
            uri: $uri,
            query: $method === 'GET' ? $payload : [],
            body: $method !== 'GET' ? $payload : [],
            headers: [],
            cookies: [],
            protocol: '1.1'
        );

        return $this->app->handle($request);
    }
}
