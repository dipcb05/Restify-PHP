<?php

declare(strict_types=1);

namespace Restify\Middleware;

use PDO;
use Restify\Http\Request;
use Restify\Http\Response;
use Restify\Support\DB;
use Restify\Support\Logging;
use Restify\Support\Schema;

final class LoggingMiddleware implements MiddlewareInterface
{
    private static bool $ensured = false;

    public function process(Request $request, callable $next): Response
    {
        $response = $next($request);

        $this->ensureTables();
        $this->recordLog($request, $response);

        return $response;
    }

    private function ensureTables(): void
    {
        if (self::$ensured) {
            return;
        }

        $connection = DB::connection();

        if (!$connection instanceof PDO) {
            return;
        }

        Schema::ensureLogsTable($connection);
        self::$ensured = true;
    }

    private function recordLog(Request $request, Response $response): void
    {
        try {
            Logging::record($request, $response);
        } catch (\Throwable) {
            // Silently swallow logging errors to avoid request failures.
        }
    }
}
