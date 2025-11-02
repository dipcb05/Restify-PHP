<?php

declare(strict_types=1);

namespace Restify\Api;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Restify\Http\Request;
use Restify\Http\Response;
use Restify\Routing\Router;
use Restify\Support\CallbackInvoker;
use RuntimeException;
use SplFileInfo;

final class ApiLoader
{
    public function __construct(
        private readonly string $directory,
        private readonly Router $router
    ) {
    }

    public function load(): void
    {
        if (!is_dir($this->directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $endpoint = $this->loadFile($file->getPathname());

            if (!$endpoint || $endpoint->isEmpty()) {
                continue;
            }

            $methods = $endpoint->methodsList();
            $handlers = $endpoint->handlers();
            $fallback = $endpoint->fallbackHandler();
            $path = $endpoint->pathValue();
            $metadata = $endpoint->metadataValue();

            if ($methods === []) {
                $methods = ['GET'];
            }

            $this->router->add($methods, $path, function (Request $request, array $parameters) use ($methods, $handlers, $fallback): Response {
                $handler = $handlers[$request->method] ?? null;

                if (!$handler && $fallback) {
                    $result = CallbackInvoker::invoke($fallback, $request, $parameters);

                    return $this->normalizeResult($result);
                }

                if (!$handler) {
                    return Response::json(
                        data: [],
                        status: 405,
                        meta: ['allowed' => $methods],
                        message: 'Method not allowed.'
                    );
                }

                $result = CallbackInvoker::invoke($handler, $request, $parameters);

                return $this->normalizeResult($result);
            }, $metadata);
        }
    }

    private function loadFile(string $file): ?ApiEndpoint
    {
        $defaultPath = $this->derivePath($file);
        $endpoint = new ApiEndpoint($defaultPath);

        $loader = static function (ApiEndpoint $endpoint, string $file): mixed {
            $api = $endpoint;

            return include $file;
        };

        $result = $loader($endpoint, $file);

        if ($result instanceof ApiEndpoint) {
            $endpoint = $result;
        } elseif (is_array($result)) {
            $endpoint = $this->applyArrayDefinition($endpoint, $result);
        } elseif (is_callable($result)) {
            $endpoint->get($result);
        }

        return $endpoint;
    }

    private function derivePath(string $file): string
    {
        $relative = substr($file, strlen($this->directory));
        $relative = str_replace('\\', '/', $relative);
        $relative = ltrim($relative, '/');
        $relative = preg_replace('/\.php$/', '', $relative);

        if ($relative === '' || $relative === false) {
            return '/api';
        }

        $segments = explode('/', $relative);

        if (end($segments) === 'index') {
            array_pop($segments);
        }

        $path = implode('/', array_filter($segments, static fn (string $segment): bool => $segment !== ''));

        if ($path === '') {
            return '/api';
        }

        return '/api/' . $path;
    }

    private function applyArrayDefinition(ApiEndpoint $endpoint, array $definition): ApiEndpoint
    {
        foreach ($definition as $method => $handler) {
            $upper = strtoupper((string) $method);

            if (in_array($upper, ['META', 'METADATA', 'OPENAPI'], true)) {
                if (is_array($handler)) {
                    $endpoint->metadata($handler);
                }

                continue;
            }

            if ($upper === 'SUMMARY') {
                $endpoint->metadata(['summary' => (string) $handler]);
                continue;
            }

            if ($upper === 'DESCRIPTION') {
                $endpoint->metadata(['description' => (string) $handler]);
                continue;
            }

            if ($upper === 'TAGS') {
                $endpoint->metadata(['tags' => (array) $handler]);
                continue;
            }

            if ($upper === 'REQUEST') {
                $endpoint->metadata(['request' => $handler]);
                continue;
            }

            if ($upper === 'RESPONSES') {
                if (is_array($handler)) {
                    $endpoint->metadata(['responses' => $handler]);
                }

                continue;
            }

            if ($upper === 'METHODS' && is_array($handler)) {
                $endpoint->metadata(['methods' => $handler]);
                continue;
            }

            if ($upper === 'PATH') {
                $endpoint->path((string) $handler);
                continue;
            }

            if ($upper === 'FALLBACK') {
                if (is_callable($handler)) {
                    $endpoint->fallback($handler);
                }

                continue;
            }

            if (!is_callable($handler)) {
                $value = $handler;
                $handler = static fn (): mixed => $value;
            }

            if ($upper === 'ANY') {
                $endpoint->any($handler);
                continue;
            }

            $endpoint->methods([$upper], $handler);
        }

        return $endpoint;
    }

    private function normalizeResult(mixed $result): Response
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
