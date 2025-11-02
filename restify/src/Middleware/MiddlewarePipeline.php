<?php

declare(strict_types=1);

namespace Restify\Middleware;

use Restify\Http\Request;
use Restify\Http\Response;

final class MiddlewarePipeline
{
    /**
     * @var array<int, callable(Request):Response>
     */
    private array $middleware = [];

    public function add(callable|string|array $middleware): void
    {
        if (is_array($middleware) && isset($middleware[0])) {
            $class = $middleware[0];
            $args = $middleware[1] ?? [];

            $this->middleware[] = static function (Request $request, callable $next) use ($class, $args): Response {
                $instance = new $class(...(array) $args);

                return $instance->process($request, $next);
            };

            return;
        }

        $this->middleware[] = $middleware;
    }

    /**
     * @param array<int, callable|string|array> $middleware
     */
    public function set(array $middleware): void
    {
        $this->middleware = [];

        foreach ($middleware as $entry) {
            $this->add($entry);
        }
    }

    /**
     * @param callable(Request):Response $destination
     */
    public function handle(Request $request, callable $destination): Response
    {
        $runner = array_reduce(
            array_reverse($this->middleware),
            fn (callable $next, callable $middleware) => $this->buildLayer($middleware, $next),
            $destination
        );

        return $runner($request);
    }

    private function buildLayer(callable|string $middleware, callable $next): callable
    {
        return static function (Request $request) use ($middleware, $next): Response {
            $callable = $middleware;

            if (is_string($middleware)) {
                /** @var MiddlewareInterface $instance */
                $instance = new $middleware();
                $callable = [$instance, 'process'];
            }

            return $callable($request, $next);
        };
    }
}
