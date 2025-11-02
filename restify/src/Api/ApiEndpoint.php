<?php

declare(strict_types=1);

namespace Restify\Api;

use Closure;
use RuntimeException;

final class ApiEndpoint
{
    private array $handlers = [];
    private $fallback = null;

    /**
        * @var array<string, mixed>
        */
    private array $metadata = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $methodMetadata = [];

    public function __construct(private string $path)
    {
        $this->path = $this->normalisePath($path);
    }

    public function path(string $path): self
    {
        $this->path = $this->normalisePath($path);

        return $this;
    }

    public function get(callable|array $handler): self
    {
        return $this->register('GET', $handler);
    }

    public function post(callable|array $handler): self
    {
        return $this->register('POST', $handler);
    }

    public function put(callable|array $handler): self
    {
        return $this->register('PUT', $handler);
    }

    public function patch(callable|array $handler): self
    {
        return $this->register('PATCH', $handler);
    }

    public function delete(callable|array $handler): self
    {
        return $this->register('DELETE', $handler);
    }

    public function options(callable|array $handler): self
    {
        return $this->register('OPTIONS', $handler);
    }

    public function methods(array $methods, callable|array $handler): self
    {
        foreach ($methods as $method) {
            $this->register(strtoupper($method), $handler);
        }

        return $this;
    }

    public function any(callable|array $handler): self
    {
        return $this->methods(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $handler);
    }

    public function fallback(callable $handler): self
    {
        $this->fallback = $handler;

        return $this;
    }

    /**
     * @return array<string, Closure>
     */
    public function handlers(): array
    {
        return $this->handlers;
    }

    public function fallbackHandler(): ?callable
    {
        return $this->fallback;
    }

    public function pathValue(): string
    {
        return $this->path;
    }

    /**
     * @return array<int, string>
     */
    public function methodsList(): array
    {
        return array_keys($this->handlers);
    }

    public function isEmpty(): bool
    {
        return $this->handlers === [];
    }

    /**
     * @param callable|array<string, mixed> $handler
     */
    private function register(string $method, callable|array $handler): self
    {
        $method = strtoupper($method);

        if (is_array($handler) && !is_callable($handler)) {
            $definition = $handler;
            $callable = $definition['handler'] ?? $definition['action'] ?? null;

            if (!is_callable($callable)) {
                throw new RuntimeException('Endpoint definition for ' . $method . ' must provide a callable handler.');
            }

            $this->applyMethodMetadata($method, $definition);
            $handler = $callable;
        }

        $this->handlers[$method] = $this->wrap($handler);

        return $this;
    }

    private function wrap(callable $handler): Closure
    {
        return $handler instanceof Closure ? $handler : Closure::fromCallable($handler);
    }

    private function normalisePath(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            return '/api';
        }

        if ($path === '/') {
            return '/';
        }

        return '/' . ltrim($path, '/');
    }

    public function metadata(array $metadata): self
    {
        $normalised = $this->normaliseMetadata($metadata);
        $this->metadata = $this->mergeMetadata($this->metadata, $normalised);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadataValue(): array
    {
        if ($this->methodMetadata === []) {
            return $this->metadata;
        }

        return $this->metadata + ['methods' => $this->methodMetadata];
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function applyMethodMetadata(string $method, array $metadata): void
    {
        if (isset($metadata['meta']) && is_array($metadata['meta'])) {
            $metadata = array_replace($metadata, $metadata['meta']);
            unset($metadata['meta']);
        }

        $normalised = $this->normaliseMethodMetadata($metadata);

        $existing = $this->methodMetadata[$method] ?? [];
        $this->methodMetadata[$method] = $this->mergeMetadata($existing, $normalised);
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function normaliseMetadata(array $metadata): array
    {
        if (isset($metadata['methods']) && is_array($metadata['methods'])) {
            foreach ($metadata['methods'] as $method => $definition) {
                $this->applyMethodMetadata(strtoupper((string) $method), (array) $definition);
            }

            unset($metadata['methods']);
        }

        return $this->normaliseMethodMetadata($metadata);
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function normaliseMethodMetadata(array $metadata): array
    {
        if (isset($metadata['tags'])) {
            $metadata['tags'] = $this->normaliseTags($metadata['tags']);
        }

        if (isset($metadata['request']) && !is_array($metadata['request'])) {
            $metadata['request'] = ['schema' => $metadata['request']];
        }

        return $metadata;
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $override
     * @return array<string, mixed>
     */
    private function mergeMetadata(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if ($key === 'tags') {
                $base['tags'] = $this->normaliseTags($value);
                continue;
            }

            if ($key === 'responses' && isset($base['responses'], $value) && is_array($base['responses']) && is_array($value)) {
                $base['responses'] = array_replace($base['responses'], $value);
                continue;
            }

            if ($key === 'request' && isset($base['request'], $value) && is_array($base['request']) && is_array($value)) {
                $base['request'] = array_replace($base['request'], $value);
                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }

    /**
     * @param mixed $tags
     * @return array<int, string>
     */
    private function normaliseTags(mixed $tags): array
    {
        if (!is_array($tags)) {
            $tags = [$tags];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $tag): string => trim((string) $tag),
            $tags
        ), static fn (string $tag): bool => $tag !== ''));
    }
}
