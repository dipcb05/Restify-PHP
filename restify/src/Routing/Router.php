<?php

declare(strict_types=1);

namespace Restify\Routing;

use Closure;
use Restify\Http\Request;
use Restify\Http\Response;
use Restify\Support\SchemaRegistry;
use Restify\Support\Validation\JsonSchemaValidator;

/**
 * Ultra-light HTTP router with attribute-ready API support.
 */
final class Router
{
    /**
     * @var Route[]
     */
    private array $routes = [];

    public function get(string $pattern, callable $handler, array $metadata = []): self
    {
        return $this->add(['GET'], $pattern, $handler, $metadata);
    }

    public function post(string $pattern, callable $handler, array $metadata = []): self
    {
        return $this->add(['POST'], $pattern, $handler, $metadata);
    }

    public function put(string $pattern, callable $handler, array $metadata = []): self
    {
        return $this->add(['PUT'], $pattern, $handler, $metadata);
    }

    public function patch(string $pattern, callable $handler, array $metadata = []): self
    {
        return $this->add(['PATCH'], $pattern, $handler, $metadata);
    }

    public function delete(string $pattern, callable $handler, array $metadata = []): self
    {
        return $this->add(['DELETE'], $pattern, $handler, $metadata);
    }

    /**
     * @param array<string> $methods
     */
    public function add(array $methods, string $pattern, callable $handler, array $metadata = []): self
    {
        $methods = array_map('strtoupper', $methods);
        $pattern = $pattern === '' ? '/' : $pattern;
        $pattern = '/' . ltrim($pattern, '/');

        [$regex, $parameterNames] = $this->compilePattern($pattern);

        $metadata = $this->normaliseMetadata($metadata);

        $this->routes[] = new Route(
            methods: $methods,
            pattern: $pattern,
            regex: $regex,
            parameterNames: $parameterNames,
            handler: $handler instanceof Closure ? $handler : Closure::fromCallable($handler),
            metadata: $metadata
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

            $validation = $this->validateRequest($route, $request);

            if ($validation instanceof Response) {
                return $validation;
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

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function normaliseMetadata(array $metadata): array
    {
        if (!isset($metadata['methods']) || !is_array($metadata['methods'])) {
            return $metadata;
        }

        $normalised = [];

        foreach ($metadata['methods'] as $method => $value) {
            $normalised[strtoupper((string) $method)] = is_array($value) ? $value : [];
        }

        $metadata['methods'] = $normalised;

        return $metadata;
    }

    private function validateRequest(Route $route, Request $request): ?Response
    {
        $metadata = $route->metadataFor($request->method);
        $requestMeta = $metadata['request'] ?? null;

        if (!is_array($requestMeta)) {
            return null;
        }

        $schemaDefinition = $requestMeta['schema'] ?? null;

        if ($schemaDefinition === null) {
            return null;
        }

        $schema = $this->resolveSchema($schemaDefinition);

        if ($schema === null) {
            return Response::json(
                data: [],
                status: 500,
                meta: ['schema' => $schemaDefinition],
                message: 'Schema not found.'
            );
        }

        $source = strtolower((string) ($requestMeta['source'] ?? 'body'));

        $payload = match ($source) {
            'query' => $request->query,
            'headers' => $request->headers,
            'cookies' => $request->cookies,
            default => $request->body,
        };

        $errors = JsonSchemaValidator::validate($payload, $schema);

        if ($errors === []) {
            return null;
        }

        return Response::json(
            data: [],
            status: 422,
            meta: ['errors' => $errors],
            message: 'Validation failed.'
        );
    }

    /**
     * @param array<string, mixed>|string $schema
     * @return array<string, mixed>|null
     */
    private function resolveSchema(array|string $schema): ?array
    {
        if (is_array($schema)) {
            return $schema;
        }

        return SchemaRegistry::get($schema);
    }
}
