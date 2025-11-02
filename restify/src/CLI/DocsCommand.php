<?php

declare(strict_types=1);

namespace Restify\CLI;

use JsonException;
use Restify\Core\Application;
use Restify\Routing\Route;
use Restify\Support\SchemaRegistry;
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
        return 'php restify-cli docs:openapi [--serve] [--host 127.0.0.1] [--port 8081] [--format json|yaml] [--output path]';
    }

    public function handle(array $arguments): int
    {
        $serve = false;
        $host = '127.0.0.1';
        $port = '8081';
        $format = 'json';
        $output = null;

        for ($i = 0, $count = count($arguments); $i < $count; $i++) {
            $argument = $arguments[$i];

            switch (true) {
                case $argument === '--serve':
                    $serve = true;
                    continue 2;
                case $argument === '--host' && isset($arguments[$i + 1]):
                    $host = $arguments[++$i];
                    continue 2;
                case str_starts_with($argument, '--host='):
                    $host = substr($argument, 7);
                    continue 2;
                case $argument === '--port' && isset($arguments[$i + 1]):
                    $port = $arguments[++$i];
                    continue 2;
                case str_starts_with($argument, '--port='):
                    $port = substr($argument, 7);
                    continue 2;
                case $argument === '--format' && isset($arguments[$i + 1]):
                    $format = strtolower($arguments[++$i]);
                    continue 2;
                case str_starts_with($argument, '--format='):
                    $format = strtolower(substr($argument, 9));
                    continue 2;
                case $argument === '--output' && isset($arguments[$i + 1]):
                    $output = $arguments[++$i];
                    continue 2;
                case str_starts_with($argument, '--output='):
                    $output = substr($argument, 9);
                    continue 2;
            }
        }

        if (!in_array($format, ['json', 'yaml'], true)) {
            throw new RuntimeException('Unsupported format: ' . $format);
        }

        $frameworkPath = is_dir($this->rootPath . DIRECTORY_SEPARATOR . 'restify')
            ? $this->rootPath . DIRECTORY_SEPARATOR . 'restify'
            : $this->frameworkPath;

        $application = $this->bootstrapApplication($frameworkPath);
        $specification = $this->buildSpecification($application);

        $docsDir = $this->rootPath . DIRECTORY_SEPARATOR . 'docs';
        $swaggerDir = $docsDir . DIRECTORY_SEPARATOR . 'swagger';

        if (!is_dir($docsDir) && !mkdir($docsDir, recursive: true) && !is_dir($docsDir)) {
            throw new RuntimeException('Unable to create docs directory: ' . $docsDir);
        }

        if (!is_dir($swaggerDir) && !mkdir($swaggerDir, recursive: true) && !is_dir($swaggerDir)) {
            throw new RuntimeException('Unable to create swagger directory: ' . $swaggerDir);
        }

        [$document, $extension] = $this->encodeSpecification($specification, $format);

        $output ??= $docsDir . DIRECTORY_SEPARATOR . 'openapi.' . $extension;

        file_put_contents($output, $document);

        $this->writeSwaggerUi($swaggerDir, basename($output));

        echo 'OpenAPI document generated at: ' . $output . PHP_EOL;
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
        $schemas = [];

        /** @var Route $route */
        foreach ($routes as $route) {
            $pattern = $route->pattern;
            $paths[$pattern] ??= [];

            foreach ($route->methods as $method) {
                $metadata = $route->metadataFor($method);

                [$requestBody, $requestParameters, $requestSchemas] = $this->buildRequestComponents($metadata['request'] ?? null);
                $schemas = array_replace($schemas, $requestSchemas);

                [$responses, $responseSchemas] = $this->buildResponses($metadata['responses'] ?? []);
                $schemas = array_replace($schemas, $responseSchemas);

                $operation = [
                    'summary' => $metadata['summary'] ?? sprintf('Handler for %s %s', $method, $pattern),
                    'responses' => $responses,
                ];

                if (!empty($metadata['description'])) {
                    $operation['description'] = (string) $metadata['description'];
                }

                if (!empty($metadata['tags']) && is_array($metadata['tags'])) {
                    $operation['tags'] = array_values(array_unique(array_map('strval', $metadata['tags'])));
                }

                $parameters = $requestParameters;

                if (!empty($metadata['parameters']) && is_array($metadata['parameters'])) {
                    $parameters = array_merge($parameters, array_values($metadata['parameters']));
                }

                if ($parameters !== []) {
                    $operation['parameters'] = $parameters;
                }

                if ($requestBody !== null) {
                    $operation['requestBody'] = $requestBody;
                }

                if (!empty($metadata['security']) && is_array($metadata['security'])) {
                    $operation['security'] = array_values($metadata['security']);
                }

                $paths[$pattern][strtolower($method)] = $operation;
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
            'components' => [
                'schemas' => $schemas,
            ],
        ];
    }

    /**
     * @return array{0:?array,1:array<int, array<string, mixed>>,2:array<string, array<string, mixed>>}
     */
    private function buildRequestComponents(?array $request): array
    {
        if ($request === null) {
            return [null, [], []];
        }

        $schemaDefinition = $request['schema'] ?? null;

        if ($schemaDefinition === null) {
            return [null, [], []];
        }

        $schemas = [];
        $schemaArray = null;

        if (is_string($schemaDefinition)) {
            $schemaArray = SchemaRegistry::get($schemaDefinition);

            if (is_array($schemaArray)) {
                $schemas[$schemaDefinition] = $schemaArray;
            }
        } elseif (is_array($schemaDefinition)) {
            $schemaArray = $schemaDefinition;
        }

        $source = strtolower((string) ($request['source'] ?? 'body'));
        $required = array_key_exists('required', $request) ? (bool) $request['required'] : true;

        if (in_array($source, ['query', 'headers', 'cookies'], true)) {
            if (!is_array($schemaArray)) {
                return [null, [], $schemas];
            }

            $location = match ($source) {
                'headers' => 'header',
                'cookies' => 'cookie',
                default => 'query',
            };

            return [null, $this->schemaToParameters($schemaArray, $location), $schemas];
        }

        $contentType = match ($source) {
            'form' => 'application/x-www-form-urlencoded',
            'multipart' => 'multipart/form-data',
            default => 'application/json',
        };

        $schemaReference = is_string($schemaDefinition) && isset($schemas[$schemaDefinition])
            ? ['$ref' => '#/components/schemas/' . $schemaDefinition]
            : ($schemaArray ?? ['type' => 'object']);

        $requestBody = [
            'required' => $required,
            'content' => [
                $contentType => [
                    'schema' => $schemaReference,
                ],
            ],
        ];

        if (isset($request['description'])) {
            $requestBody['description'] = (string) $request['description'];
        }

        if (isset($request['example'])) {
            $requestBody['content'][$contentType]['example'] = $request['example'];
        }

        if (isset($request['examples']) && is_array($request['examples'])) {
            $requestBody['content'][$contentType]['examples'] = $request['examples'];
        }

        return [$requestBody, [], $schemas];
    }

    /**
     * @return array{0:array<string, mixed>,1:array<string, array<string, mixed>>}
     */
    private function buildResponses(array $responses): array
    {
        $documents = [];
        $schemas = [];

        foreach ($responses as $status => $definition) {
            $statusCode = (string) $status;
            $response = [
                'description' => 'Response',
            ];

            $content = [];

            if (is_array($definition)) {
                if (isset($definition['description'])) {
                    $response['description'] = (string) $definition['description'];
                }

                if (isset($definition['schema'])) {
                    $schemaDefinition = $definition['schema'];

                    if (is_string($schemaDefinition)) {
                        $schema = SchemaRegistry::get($schemaDefinition);

                        if (is_array($schema)) {
                            $schemas[$schemaDefinition] = $schema;
                            $content['application/json']['schema'] = ['$ref' => '#/components/schemas/' . $schemaDefinition];
                        }
                    } elseif (is_array($schemaDefinition)) {
                        $content['application/json']['schema'] = $schemaDefinition;
                    }
                }

                if (isset($definition['example'])) {
                    $content['application/json']['example'] = $definition['example'];
                }

                if (isset($definition['examples']) && is_array($definition['examples'])) {
                    $content['application/json']['examples'] = $definition['examples'];
                }
            } else {
                $response['description'] = (string) $definition;
            }

            if ($content !== []) {
                $response['content'] = $content;
            }

            $documents[$statusCode] = $response;
        }

        if ($documents === []) {
            $documents['200'] = ['description' => 'Successful response'];
        }

        if (!isset($documents['default'])) {
            $documents['default'] = ['description' => 'Unexpected error'];
        }

        return [$documents, $schemas];
    }

    /**
     * @param array<string, mixed> $schema
     * @return array<int, array<string, mixed>>
     */
    private function schemaToParameters(array $schema, string $location): array
    {
        $parameters = [];
        $properties = $schema['properties'] ?? [];
        $required = $schema['required'] ?? [];
        $required = is_array($required) ? $required : [];

        foreach ($properties as $name => $definition) {
            if (!is_array($definition)) {
                continue;
            }

            $parameter = [
                'name' => (string) $name,
                'in' => $location,
                'required' => in_array($name, $required, true),
                'schema' => $definition,
            ];

            if (isset($definition['description'])) {
                $parameter['description'] = (string) $definition['description'];
                unset($parameter['schema']['description']);
            }

            if (isset($definition['example'])) {
                $parameter['example'] = $definition['example'];
                unset($parameter['schema']['example']);
            }

            $parameters[] = $parameter;
        }

        return $parameters;
    }

    /**
     * @return array{0:string,1:string}
     */
    private function encodeSpecification(array $specification, string $format): array
    {
        if ($format === 'yaml') {
            return [$this->renderYaml($specification), 'yaml'];
        }

        try {
            $json = json_encode($specification, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Failed to encode OpenAPI document: ' . $exception->getMessage(), 0, $exception);
        }

        return [$json, 'json'];
    }

    private function renderYaml(mixed $data, int $indent = 0): string
    {
        $indentation = str_repeat('  ', $indent);

        if (is_array($data)) {
            if ($data === []) {
                return $indentation . '[]';
            }

            if (array_is_list($data)) {
                $lines = [];

                foreach ($data as $value) {
                    if (is_array($value)) {
                        $lines[] = $indentation . '-';
                        $lines[] = $this->renderYaml($value, $indent + 1);
                    } else {
                        $lines[] = $indentation . '- ' . $this->yamlScalar($value);
                    }
                }

                return implode(PHP_EOL, $lines);
            }

            $lines = [];

            foreach ($data as $key => $value) {
                $keyScalar = $this->yamlScalar((string) $key);

                if (is_array($value)) {
                    if ($value === []) {
                        $lines[] = $indentation . $keyScalar . ': []';
                        continue;
                    }

                    $lines[] = $indentation . $keyScalar . ':';
                    $lines[] = $this->renderYaml($value, $indent + 1);
                    continue;
                }

                $lines[] = $indentation . $keyScalar . ': ' . $this->yamlScalar($value);
            }

            return implode(PHP_EOL, $lines);
        }

        return $indentation . $this->yamlScalar($data);
    }

    private function yamlScalar(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        $string = (string) $value;

        if ($string === '') {
            return "''";
        }

        if (preg_match('/[:{}\[\],&*#?|\-<>=!%@\r\n\s]/', $string)) {
            return "'" . str_replace("'", "''", $string) . "'";
        }

        return $string;
    }

    private function writeSwaggerUi(string $swaggerDir, string $specFile): void
    {
        $html = <<<HTML
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
            url: '../{$specFile}',
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
