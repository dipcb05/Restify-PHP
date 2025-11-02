<?php

declare(strict_types=1);

namespace Tests;

use Restify\Http\Response;
use Restify\Testing\Assertions\Assert;
use Restify\Testing\TestCase;

final class ExampleTest extends TestCase
{
    public function testRootRouteResponds(): void
    {
        $response = $this->call('GET', '/');

        Assert::equals(Response::json([])->headers['Content-Type'], $response->headers['Content-Type'] ?? null);
        Assert::status($response, 200);
    }
}

