<?php

declare(strict_types=1);

namespace Restify\Middleware;

use Restify\Http\Request;
use Restify\Http\Response;

/**
 * Simple middleware pipeline executor.
 */
final class MiddlewarePipeline
{
    /**
     * @var array<int, callable(Request):Response|class-string<MiddlewareInterface>>
     */
    private array $middleware = [];

    public function add(callable|string $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    /**
     * @param array<int, callable|string> $middleware
     */
    public function set(array $middleware): void
    {
        $this->middleware = [];
        foreach ($middleware as $entry) {
            $this->add($entry);
        }
    }

    /**
     * Execute the pipeline and produce a response.
     *
     * @param callable(Request):Response $destination
     */
    public function handle(Request $request, callable $destination): Response
    {
        $runner = array_reduce(
            array_reverse($this->middleware),
            fn (callable $next, callable|string $middleware) => $this->buildLayer($middleware, $next),
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
