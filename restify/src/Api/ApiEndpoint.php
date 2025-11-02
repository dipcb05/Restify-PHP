<?php

declare(strict_types=1);

namespace Restify\Api;

use Closure;

final class ApiEndpoint
{
    private array $handlers = [];

    private $fallback = null;

    public function __construct(private string $path)
    {
        $this->path = $this->normalisePath($path);
    }

    public function path(string $path): self
    {
        $this->path = $this->normalisePath($path);

        return $this;
    }

    public function get(callable $handler): self
    {
        return $this->register('GET', $handler);
    }

    public function post(callable $handler): self
    {
        return $this->register('POST', $handler);
    }

    public function put(callable $handler): self
    {
        return $this->register('PUT', $handler);
    }

    public function patch(callable $handler): self
    {
        return $this->register('PATCH', $handler);
    }

    public function delete(callable $handler): self
    {
        return $this->register('DELETE', $handler);
    }

    public function options(callable $handler): self
    {
        return $this->register('OPTIONS', $handler);
    }

    public function methods(array $methods, callable $handler): self
    {
        foreach ($methods as $method) {
            $this->register(strtoupper($method), $handler);
        }

        return $this;
    }

    public function any(callable $handler): self
    {
        return $this->methods(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $handler);
    }

    public function fallback(callable $handler): self
    {
        $this->fallback = $handler;

        return $this;
    }

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

    public function methodsList(): array
    {
        return array_keys($this->handlers);
    }

    public function isEmpty(): bool
    {
        return $this->handlers === [];
    }

    private function register(string $method, callable $handler): self
    {
        $this->handlers[strtoupper($method)] = $this->wrap($handler);

        return $this;
    }

    private function wrap(callable $handler): callable
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
}
