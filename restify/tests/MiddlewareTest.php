<?php

declare(strict_types=1);

namespace Tests;

use Restify\Http\Request;
use Restify\Http\Response;
use Restify\Routing\Router;
use Restify\Testing\Assertions\Assert;
use Restify\Testing\TestCase;

final class MiddlewareTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $_ENV['AUTH_ENABLED'] = 'false';
        $_ENV['LOGGING_ENABLED'] = 'false';
        $_ENV['LOG_DATABASE_ENABLED'] = 'false';
    }

    public function testCorsPreflightRespondsWithConfiguredHeaders(): void
    {
        $request = new Request(
            method: 'OPTIONS',
            uri: '/health',
            query: [],
            body: [],
            headers: [
                'Origin' => 'https://example.com',
                'Access-Control-Request-Method' => 'GET',
            ],
            cookies: [],
            protocol: '1.1'
        );

        $response = $this->app->handle($request);

        Assert::status($response, 204);
        Assert::equals('*', $response->headers['Access-Control-Allow-Origin'] ?? null);
        Assert::equals('Origin', $response->headers['Vary'] ?? null);
        Assert::equals(
            'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            $response->headers['Access-Control-Allow-Methods'] ?? null
        );
    }

    public function testExceptionMiddlewareReturnsJsonEnvelope(): void
    {
        $this->registerRoute('/boom', function (): void {
            throw new \RuntimeException('Boom');
        });

        $response = $this->call('GET', '/boom');

        Assert::status($response, 500);

        $payload = $this->decode($response);

        Assert::equals(false, $payload['ok']);
        Assert::equals(500, $payload['status']);
        Assert::equals('Server Error', $payload['message']);
        Assert::equals('Boom', $payload['meta']['error']['message'] ?? null);
    }

    public function testJsonSchemaValidationProduces422WhenInvalid(): void
    {
        $this->registerRoute(
            '/schema-check',
            static fn () => ['ok' => true],
            [
                'request' => [
                    'schema' => [
                        'type' => 'object',
                        'required' => ['name'],
                        'properties' => [
                            'name' => ['type' => 'string', 'minLength' => 3],
                        ],
                    ],
                ],
            ],
            methods: ['POST']
        );

        $response = $this->call('POST', '/schema-check', ['name' => 'Jo']);

        Assert::status($response, 422);

        $payload = $this->decode($response);

        Assert::equals(false, $payload['ok']);
        Assert::equals('Validation failed.', $payload['message']);
        Assert::equals(
            '$.name: length must be at least 3 characters',
            $payload['meta']['errors'][0] ?? null
        );
    }

    private function registerRoute(string $path, callable $handler, array $metadata = [], array $methods = ['GET']): void
    {
        /** @var Router $router */
        $router = $this->app->router();
        $router->add($methods, $path, $handler, $metadata);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        /** @var array<string, mixed> $payload */
        $payload = json_decode($response->content, true, 512, JSON_THROW_ON_ERROR);

        return $payload;
    }
}
