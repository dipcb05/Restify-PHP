<?php

declare(strict_types=1);

namespace Tests;

use Restify\Http\Response;
use Restify\Testing\Assertions\Assert;
use Restify\Testing\TestCase;

final class ExampleTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $_ENV['AUTH_ENABLED'] = 'false';
        $_ENV['LOGGING_ENABLED'] = 'false';
        $_ENV['LOG_DATABASE_ENABLED'] = 'false';
    }

    public function testHealthEndpointResponds(): void
    {
        $response = $this->call('GET', '/health');

        Assert::equals(Response::json([])->headers['Content-Type'], $response->headers['Content-Type'] ?? null);
        Assert::status($response, 200);

        /** @var array<string, mixed> $payload */
        $payload = json_decode($response->content, true, 512, JSON_THROW_ON_ERROR);

        Assert::equals(true, $payload['ok'] ?? false);
        Assert::equals('ok', $payload['data']['status'] ?? null);
    }
}
