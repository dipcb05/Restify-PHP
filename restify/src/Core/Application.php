<?php

declare(strict_types=1);

namespace Restify\Core;

use ReflectionClass;
use ReflectionMethod;
use Restify\Api\ApiLoader;
use Restify\Http\Request;
use Restify\Http\Response;
use Restify\Middleware\MiddlewarePipeline;
use Restify\Routing\Attributes\Route as RouteAttribute;
use Restify\Routing\Router;
use Restify\Support\CallbackInvoker;

final class Application
{
    /**
     * @param array<string, string> $environment
     */
    private Router $router;
    private MiddlewarePipeline $pipeline;

    public function __construct(
        private readonly string $basePath,
        private readonly string $rootPath,
        private readonly array $environment = [],
        ?Router $router = null,
        ?MiddlewarePipeline $pipeline = null,
    ) {
        $this->router = $router ?? new Router();
        $this->pipeline = $pipeline ?? new MiddlewarePipeline();

        $this->configureMiddleware();
        $this->registerRoutes();
    }

    public function handle(?Request $request = null): Response
    {
        $request ??= Request::capture();

        return $this->pipeline->handle($request, fn (Request $incoming): Response => $this->router->dispatch($incoming));
    }

    public function router(): Router
    {
        return $this->router;
    }

    /**
     * @return array<string, string>
     */
    public function env(): array
    {
        return $this->environment;
    }

    private function configureMiddleware(): void
    {
        $middlewareConfig = $this->loadConfig('middleware.php');

        if (isset($middlewareConfig['global']) && is_array($middlewareConfig['global'])) {
            $this->pipeline->set($middlewareConfig['global']);
        }
    }

    private function registerRoutes(): void
    {
        $this->registerRouteFiles();
        $this->registerApiDirectory();
        $this->registerAttributedRoutes();
    }

    /**
     * @return array<string, mixed>
     */
    private function loadConfig(string $file): array
    {
        $path = $this->basePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . $file;

        if (!is_file($path)) {
            return [];
        }

        /** @var array<string, mixed> $config */
        $config = require $path;

        return $config;
    }

    private function registerRouteFiles(): void
    {
        $routeDirectory = $this->basePath . DIRECTORY_SEPARATOR . 'routes';
        $candidates = ['web.php', 'api.php'];

        foreach ($candidates as $candidate) {
            $path = $routeDirectory . DIRECTORY_SEPARATOR . $candidate;

            if (!is_file($path)) {
                continue;
            }

            $registrar = require $path;

            if (is_callable($registrar)) {
                $registrar($this->router);
            }
        }
    }

    private function registerApiDirectory(): void
    {
        $directory = $this->rootPath . DIRECTORY_SEPARATOR . 'api';

        if (!is_dir($directory)) {
            return;
        }

        $loader = new ApiLoader($directory, $this->router);
        $loader->load();
    }

    private function registerAttributedRoutes(): void
    {
        foreach (get_declared_classes() as $class) {
            if (!class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract()) {
                continue;
            }

            $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

            foreach ($methods as $method) {
                $attributes = $method->getAttributes(RouteAttribute::class);

                if ($attributes === []) {
                    continue;
                }

                $instance = $method->isStatic() ? null : $this->instantiateRouteClass($reflection);

                if (!$method->isStatic() && $instance === null) {
                    continue;
                }

                foreach ($attributes as $attribute) {
                    /** @var RouteAttribute $route */
                    $route = $attribute->newInstance();
                    $metadata = $route->metadata();

                    $this->router->add(
                        $route->methods,
                        $route->path,
                        function (Request $request, array $parameters) use ($instance, $method) {
                            return $this->invokeAttributedMethod($instance, $method, $request, $parameters);
                        },
                        $metadata
                    );
                }
            }
        }
    }

    private function instantiateRouteClass(ReflectionClass $class): ?object
    {
        if (!$class->isInstantiable()) {
            return null;
        }

        $constructor = $class->getConstructor();

        if ($constructor && $constructor->getNumberOfRequiredParameters() > 0) {
            return null;
        }

        return $class->newInstance();
    }

    private function invokeAttributedMethod(?object $instance, ReflectionMethod $method, Request $request, array $parameters): mixed
    {
        $callable = $method->isStatic()
            ? [$method->getDeclaringClass()->getName(), $method->getName()]
            : [$instance, $method->getName()];

        return CallbackInvoker::invoke($callable, $request, $parameters);
    }
}
