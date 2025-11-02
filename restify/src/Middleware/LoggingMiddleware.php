<?php

declare(strict_types=1);

namespace Restify\Middleware;

use PDO;
use Restify\Http\Request;
use Restify\Http\Response;
use Restify\Support\Config;
use Restify\Support\DB;
use Restify\Support\Logger;
use Restify\Support\Logging;
use Restify\Support\Schema;
use Throwable;

final class LoggingMiddleware implements MiddlewareInterface
{
    private static bool $ensured = false;

    /**
     * @var array<string, mixed>
     */
    private array $config;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config ?: Config::get('logging', []);
    }

    public function process(Request $request, callable $next): Response
    {
        if (!(bool) ($this->config['enabled'] ?? true)) {
            return $next($request);
        }

        $start = microtime(true);
        $context = [
            'request' => $this->requestContext($request),
        ];

        try {
            $response = $next($request);
        } catch (Throwable $throwable) {
            $context['duration_ms'] = $this->duration($start);
            $context['exception'] = $this->exceptionContext($throwable);

            $this->writeLog('error', $request, new Response('', 500), $context);

            throw $throwable;
        }

        $context['response'] = $this->responseContext($response);
        $context['duration_ms'] = $this->duration($start);

        $level = $this->determineLevel($response->status);

        $this->writeLog($level, $request, $response, $context);

        return $response;
    }

    private function writeLog(string $level, Request $request, Response $response, array $context): void
    {
        $message = sprintf(
            '%s %s -> %d (%d ms)',
            $request->method,
            $request->uri,
            $response->status,
            (int) ($context['duration_ms'] ?? 0)
        );

        try {
            Logger::log($level, $message, $context, $this->config);

            if (($this->config['database']['enabled'] ?? true) === true) {
                $this->ensureTables();
                Logging::record($request, $response, $level, $context);
            }
        } catch (Throwable) {
            // swallow logging failures to avoid breaking the request lifecycle
        }
    }

    private function ensureTables(): void
    {
        if (self::$ensured) {
            return;
        }

        $connection = DB::connection();

        if (!$connection instanceof PDO) {
            return;
        }

        Schema::ensureLogsTable($connection);
        self::$ensured = true;
    }

    private function duration(float $start): int
    {
        return (int) round((microtime(true) - $start) * 1000);
    }

    private function requestContext(Request $request): array
    {
        $context = [
            'method' => $request->method,
            'uri' => $request->uri,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ];

        if (($this->config['request']['enabled'] ?? true) === true) {
            $context['query'] = $this->sanitizeArray($request->query);

            if (($this->config['request']['body'] ?? true) === true) {
                $context['body'] = $this->sanitizeArray($request->body);
                if ($request->rawBody !== null) {
                    $context['raw'] = $this->truncate($request->rawBody);
                }
            }

            if (($this->config['request']['headers'] ?? false) === true) {
                $context['headers'] = $request->headers;
            }
        }

        return $context;
    }

    private function responseContext(Response $response): array
    {
        $context = [
            'status' => $response->status,
        ];

        if (($this->config['response']['enabled'] ?? true) === true) {
            if (($this->config['response']['headers'] ?? false) === true) {
                $context['headers'] = $response->headers;
            }

            if (($this->config['response']['body'] ?? false) === true) {
                $context['body'] = $this->truncate($response->content);
            }
        }

        return $context;
    }

    private function exceptionContext(Throwable $throwable): array
    {
        return [
            'type' => $throwable::class,
            'message' => $throwable->getMessage(),
            'code' => $throwable->getCode(),
        ];
    }

    private function determineLevel(int $status): string
    {
        $map = $this->config['level_map'] ?? [
            'info' => [200, 399],
            'warning' => [400, 499],
            'error' => [500, 599],
        ];

        foreach ($map as $level => $range) {
            $lower = (int) ($range[0] ?? 0);
            $upper = (int) ($range[1] ?? 599);

            if ($status >= $lower && $status <= $upper) {
                return (string) $level;
            }
        }

        return (string) ($this->config['level'] ?? 'info');
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function sanitizeArray(array $data): array
    {
        $sanitized = [];
        $sensitive = $this->sensitiveFields();

        foreach ($data as $key => $value) {
            $normalizedKey = is_string($key) ? strtolower($key) : (string) $key;

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArray($value);
                continue;
            }

            if (in_array($normalizedKey, $sensitive, true)) {
                $sanitized[$key] = '[redacted]';
                continue;
            }

            if (is_string($value)) {
                $sanitized[$key] = $this->truncate($value);
                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    private function truncate(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $limit = (int) ($this->config['max_body_length'] ?? 2048);

        if ($limit <= 0 || strlen($value) <= $limit) {
            return $value;
        }

        return substr($value, 0, $limit) . '...';
    }

    /**
     * @return array<int, string>
     */
    private function sensitiveFields(): array
    {
        $fields = $this->config['sensitive_fields'] ?? [];

        if (!is_array($fields)) {
            return [];
        }

        return array_values(array_unique(array_map(static fn ($value): string => strtolower((string) $value), $fields)));
    }
}
