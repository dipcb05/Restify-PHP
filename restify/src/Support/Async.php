<?php

declare(strict_types=1);

namespace Restify\Support;

use Restify\Core\Async as AsyncCore;
use Restify\Http\Response;

final class Async
{
    private function __construct()
    {
    }

    public static function run(callable $callback): mixed
    {
        return AsyncCore::instance()->run($callback);
    }

    public static function parallel(array $callbacks): array
    {
        return AsyncCore::instance()->parallel($callbacks);
    }

    public static function http(string|array $request, array $options = []): array
    {
        return AsyncCore::instance()->http($request, $options);
    }

    public static function socket(string $host, int $port, string $payload = '', float $timeout = 5.0): string
    {
        return AsyncCore::instance()->socket($host, $port, $payload, $timeout);
    }

    public static function background(string $script, array $arguments = []): void
    {
        AsyncCore::instance()->background($script, $arguments);
    }

    public static function json(array $data, int $status = 200, array $meta = [], ?string $message = null): Response
    {
        return AsyncCore::instance()->json($data, $status, $meta, $message);
    }

    public static function isSupported(): bool
    {
        if (AsyncCore::supportsFibers()) {
            return true;
        }

        if (extension_loaded('swoole') || extension_loaded('swoole_coroutine')) {
            return true;
        }

        return class_exists('Workerman\\Worker');
    }
}
