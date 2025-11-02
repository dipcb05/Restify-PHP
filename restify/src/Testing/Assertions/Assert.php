<?php

declare(strict_types=1);

namespace Restify\Testing\Assertions;

use Restify\Http\Request;
use Restify\Http\Response;

final class Assert
{
    private function __construct()
    {
    }

    public static function equals(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            throw new AssertionFailed(
                $message !== '' ? $message : sprintf('Failed asserting that %s matches expected %s.', var_export($actual, true), var_export($expected, true))
            );
        }
    }

    public static function true(bool $value, string $message = ''): void
    {
        self::equals(true, $value, $message !== '' ? $message : 'Failed asserting that value is true.');
    }

    public static function false(bool $value, string $message = ''): void
    {
        self::equals(false, $value, $message !== '' ? $message : 'Failed asserting that value is false.');
    }

    public static function status(Response $response, int $expected): void
    {
        self::equals($expected, $response->status, 'Unexpected response status.');
    }

    public static function json(Response $response): void
    {
        self::true(str_contains($response->headers['Content-Type'] ?? '', 'application/json'), 'Response is not JSON.');
    }

    public static function requestMethod(Request $request, string $expected): void
    {
        self::equals(strtoupper($expected), $request->method, 'Unexpected request method.');
    }
}
