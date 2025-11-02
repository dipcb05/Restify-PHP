<?php

declare(strict_types=1);

namespace Restify\Middleware;

use Restify\Http\Request;
use Restify\Http\Response;
use Restify\Support\Logging;

final class LoggingMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $response = $next($request);

        try {
            Logging::record($request, $response);
        } catch (\Throwable) {
            // Silently ignore logging failures to avoid impacting the request lifecycle.
        }

        return $response;
    }
}
