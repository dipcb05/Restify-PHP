<?php

declare(strict_types=1);

namespace Restify\CLI;

use JsonException;
use Restify\Core\Application;
use Restify\Routing\Route;
use RuntimeException;

final class DocsCommand implements CommandContract
{
    public function __construct(private readonly string $rootPath, private readonly string $frameworkPath)
    {
    }

    public function signature(): string
    {
        return 'docs:openapi';
    }

    public function description(): string
    {
        return 'Generate OpenAPI documentation and optional Swagger UI server.';
    }

    public function usage(): string
    {
        return 'php restify-cli docs:openapi [--serve] [--host 127.0.0.1] [--port 8081]';
    }

    public function handle(array $arguments): int
    {
        $serve = false;
        $host = '127.0.0.1';
        $port = '8081';
        $count = count($arguments);

        for ($i = 0; $i < $count; $i++) {
            $argument = $arguments[$i];

            if ($argument === '--serve') {
                $serve = true;
                continue;
            }

            if ($argument === '--host' && isset($arguments[$i + 1])) {
                $host = $arguments[++$i];
                continue;
            }

            if (str_starts_with($argument, '--host=')) {
                $host = substr($argument, 7);
                continue;
            }

            if ($argument === '--port' && isset($arguments[$i + 1])) {
                $port = $arguments[++$i];
                continue;
            }

            if (str_starts_with($argument, '--port=')) {
                $port = substr($argument, 7);
                continue;
            }
        }

        $frameworkPath = is_dir($this->rootPath . DIRECTORY_SEPARATOR . 'restify')
            ? $this->rootPath . DIRECTORY_SEPARATOR . 'restify'
            : $this->frameworkPath;
        $application = $this->bootstrapApplication($frameworkPath);

        $spec = $this->buildSpecification($application);
        $docsDir = $this->rootPath . DIRECTORY_SEPARATOR . 'docs';
        $swaggerDir = $docsDir . DIRECTORY_SEPARATOR . 'swagger';

        if (!is_dir($swaggerDir) && !mkdir($swaggerDir, recursive: true) && !is_dir($swaggerDir)) {
            throw new RuntimeException('Unable to create swagger directory: ' . $swaggerDir);
        }

        $openapiFile = $docsDir . DIRECTORY_SEPARATOR . 'openapi.json';

        try {
            file_put_contents(
                $openapiFile,
                json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
            );
        } catch (JsonException $exception) {
            throw new RuntimeException('Failed to encode OpenAPI document: ' . $exception->getMessage(), 0, $exception);
        }

        $this->writeSwaggerUi($swaggerDir);

        echo "OpenAPI document generated at: {$openapiFile}" . PHP_EOL;
        echo 'Swagger UI available at: ' . $swaggerDir . DIRECTORY_SEPARATOR . 'index.html' . PHP_EOL;

        if ($serve) {
            $command = sprintf(
                '%s -S %s:%s -t %s',
                escapeshellarg(PHP_BINARY),
                $host,
                $port,
                escapeshellarg($swaggerDir)
            );

            echo "Serving Swagger UI on http://{$host}:{$port}" . PHP_EOL;
            passthru($command);
        }

        return 0;
    }

    private function bootstrapApplication(string $frameworkPath): Application
    {
        $bootstrap = $frameworkPath . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php';

        if (!is_file($bootstrap)) {
            throw new RuntimeException('Unable to locate Restify bootstrap file.');
        }

        /** @var Application $application */
        $application = require $bootstrap;

        return $application;
    }

    private function buildSpecification(Application $application): array
    {
        $router = $application->router();
        $routes = $router->routes();

        $paths = [];

        /** @var Route $route */
        foreach ($routes as $route) {
            $pattern = $route->pattern;
            $paths[$pattern] ??= [];

            foreach ($route->methods as $method) {
                $paths[$pattern][strtolower($method)] = [
                    'summary' => sprintf('Handler for %s %s', $method, $pattern),
                    'responses' => [
                        '200' => [
                            'description' => 'Successful response',
                        ],
                        'default' => [
                            'description' => 'Unexpected error',
                        ],
                    ],
                ];
            }
        }

        $env = $application->env();

        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => $env['APP_NAME'] ?? 'Restify API',
                'version' => $env['APP_VERSION'] ?? '1.0.0',
                'description' => 'Automatically generated documentation by Restify-PHP.',
            ],
            'servers' => [
                [
                    'url' => $env['APP_URL'] ?? 'http://localhost:8000',
                ],
            ],
            'paths' => $paths,
        ];
    }

    private function writeSwaggerUi(string $swaggerDir): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Restify Swagger UI</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
    <style>
        html, body { margin: 0; padding: 0; height: 100%; }
        #swagger-ui { height: 100%; }
    </style>
</head>
<body>
<div id="swagger-ui"></div>
<script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
<script>
    window.onload = () => {
        SwaggerUIBundle({
            url: '../openapi.json',
            dom_id: '#swagger-ui',
            presets: [
                SwaggerUIBundle.presets.apis,
                SwaggerUIBundle.SwaggerUIStandalonePreset
            ],
        });
    };
</script>
</body>
</html>
HTML;

        file_put_contents($swaggerDir . DIRECTORY_SEPARATOR . 'index.html', $html);
    }
}

