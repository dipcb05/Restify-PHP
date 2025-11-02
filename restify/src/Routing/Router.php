<?php

declare(strict_types=1);

namespace Restify\Routing;

use Closure;
use Restify\Http\Request;
use Restify\Http\Response;

/**
 * Ultra-light HTTP router with attribute-ready API support.
 */
final class Router
{
    /**
     * @var Route[]
     */
    private array $routes = [];

    public function get(string $pattern, callable $handler): self
    {
        return $this->add(['GET'], $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): self
    {
        return $this->add(['POST'], $pattern, $handler);
    }

    public function put(string $pattern, callable $handler): self
    {
        return $this->add(['PUT'], $pattern, $handler);
    }

    public function patch(string $pattern, callable $handler): self
    {
        return $this->add(['PATCH'], $pattern, $handler);
    }

    public function delete(string $pattern, callable $handler): self
    {
        return $this->add(['DELETE'], $pattern, $handler);
    }

    /**
     * @param array<string> $methods
     */
    public function add(array $methods, string $pattern, callable $handler): self
    {
        $methods = array_map('strtoupper', $methods);
        $pattern = $pattern === '' ? '/' : $pattern;
        $pattern = '/' . ltrim($pattern, '/');

        [$regex, $parameterNames] = $this->compilePattern($pattern);

        $this->routes[] = new Route(
            methods: $methods,
            pattern: $pattern,
            regex: $regex,
            parameterNames: $parameterNames,
            handler: $handler instanceof Closure ? $handler : Closure::fromCallable($handler)
        );

        return $this;
    }

    /**
     * @return Route[]
     */
    public function routes(): array
    {
        return $this->routes;
    }

    public function dispatch(Request $request): Response
    {
        foreach ($this->routes as $route) {
            if (!in_array($request->method, $route->methods, true)) {
                continue;
            }

            if (!preg_match($route->regex, $request->uri, $matches)) {
                continue;
            }

            $parameters = [];
            foreach ($route->parameterNames as $index => $name) {
                $parameters[$name] = $matches[$index + 1] ?? null;
            }

            $result = ($route->handler)(
                $request,
                $parameters
            );

            return $this->normalizeResponse($result);
        }

        return Response::json(
            data: [],
            status: 404,
            message: 'Route not found.'
        );
    }

    private function compilePattern(string $pattern): array
    {
        $parameterNames = [];

        $regex = preg_replace_callback(
            pattern: '/\{([\w]+)\}/',
            callback: static function (array $matches) use (&$parameterNames) {
                $parameterNames[] = $matches[1];

                return '([^/]+)';
            },
            subject: $pattern
        );

        $regex = '#^' . ($regex ?? $pattern) . '$#u';

        return [$regex, $parameterNames];
    }

    private function normalizeResponse(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        if (is_array($result)) {
            return Response::json($result);
        }

        return Response::text((string) $result);
    }
}
