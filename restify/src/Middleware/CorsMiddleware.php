<?php

declare(strict_types=1);

namespace Restify\Middleware;

use Restify\Http\Request;
use Restify\Http\Response;

final class CorsMiddleware implements MiddlewareInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(private readonly array $config = [])
    {
    }

    public function process(Request $request, callable $next): Response
    {
        if (!(bool) ($this->config['enabled'] ?? true)) {
            return $next($request);
        }

        $origin = $request->headers['Origin'] ?? null;
        $allowedOrigin = $this->resolveOrigin($origin);
        $corsHeaders = $this->corsHeaders($request, $allowedOrigin);

        if ($request->method === 'OPTIONS') {
            $headers = $corsHeaders;
            $headers['Content-Length'] ??= '0';

            return new Response(
                content: '',
                status: 204,
                headers: $headers
            );
        }

        $response = $next($request);

        return $this->appendHeaders($response, $corsHeaders);
    }

    private function resolveOrigin(?string $origin): string
    {
        $allowed = $this->config['allowed_origins'] ?? ['*'];

        if (in_array('*', $allowed, true)) {
            return '*';
        }

        if ($origin && $this->originAllowed($origin, $allowed)) {
            return $origin;
        }

        return $allowed[0] ?? '*';
    }

    /**
     * @param array<int, string> $allowed
     */
    private function originAllowed(string $origin, array $allowed): bool
    {
        foreach ($allowed as $candidate) {
            if ($candidate === $origin) {
                return true;
            }

            if (str_contains($candidate, '*')) {
                $pattern = '#^' . str_replace('\*', '.*', preg_quote($candidate, '#')) . '$#i';

                if (preg_match($pattern, $origin) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<string, string>
     */
    private function corsHeaders(Request $request, string $origin): array
    {
        $methods = $this->config['allowed_methods'] ?? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];
        $allowedHeaders = $this->config['allowed_headers'] ?? ['Content-Type', 'Authorization', 'Accept'];
        $exposedHeaders = $this->config['exposed_headers'] ?? [];
        $maxAge = (int) ($this->config['max_age'] ?? 600);
        $credentials = (bool) ($this->config['supports_credentials'] ?? false);

        $headers = [
            'Access-Control-Allow-Origin' => $origin,
            'Vary' => 'Origin',
            'Access-Control-Allow-Methods' => implode(', ', $methods),
            'Access-Control-Allow-Headers' => implode(', ', $allowedHeaders),
        ];

        if ($request->method === 'OPTIONS') {
            $headers['Access-Control-Max-Age'] = (string) $maxAge;
        }

        if ($credentials && $origin !== '*') {
            $headers['Access-Control-Allow-Credentials'] = 'true';
        }

        if ($exposedHeaders !== []) {
            $headers['Access-Control-Expose-Headers'] = implode(', ', $exposedHeaders);
        }

        if ($allowedHeaders === []) {
            unset($headers['Access-Control-Allow-Headers']);
        }

        return $headers;
    }

    private function appendHeaders(Response $response, array $corsHeaders): Response
    {
        $headers = $response->headers;

        if (isset($headers['Vary'])) {
            $vary = array_unique(array_map('trim', explode(',', $headers['Vary'])));
            $corsVary = array_unique(array_map('trim', explode(',', $corsHeaders['Vary'] ?? '')));
            $merged = array_values(array_filter(array_unique(array_merge($vary, $corsVary))));
            $corsHeaders['Vary'] = implode(', ', $merged);
            unset($headers['Vary']);
        }

        return new Response(
            content: $response->content,
            status: $response->status,
            headers: array_merge($headers, $corsHeaders)
        );
    }
}
