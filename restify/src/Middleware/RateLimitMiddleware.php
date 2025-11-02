<?php

declare(strict_types=1);

namespace Restify\Middleware;

use Restify\Http\Request;
use Restify\Http\Response;
use Restify\Support\Cache;

final class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly int $limit = 60,
        private readonly int $seconds = 60
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        $key = $this->buildKey($request);

        if (!Cache::rateLimit($key, $this->limit, $this->seconds)) {
            return Response::json(
                data: [],
                status: 429,
                message: 'Too Many Requests.'
            );
        }

        return $next($request);
    }

    private function buildKey(Request $request): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        return md5($ip . '|' . $request->method . '|' . $request->uri);
    }
}
