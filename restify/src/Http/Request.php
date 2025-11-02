<?php

declare(strict_types=1);

namespace Restify\Http;

final class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     * @param array<string, string> $cookies
     */
    public function __construct(
        public readonly string $method,
        public readonly string $uri,
        public readonly array $query,
        public readonly array $body,
        public readonly array $headers,
        public readonly array $cookies,
        public readonly string $protocol,
        public readonly ?string $rawBody = null
    ) {
    }

    public static function capture(): self
    {
        $headers = self::gatherHeaders();
        $rawBody = file_get_contents('php://input') ?: null;
        $body = $_POST;

        if ($rawBody !== null && self::isJson($headers)) {
            $decoded = json_decode($rawBody, true);

            if (is_array($decoded)) {
                $body = $decoded;
            }
        }

        $uri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';

        return new self(
            method: strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            uri: $uri,
            query: $_GET,
            body: $body,
            headers: $headers,
            cookies: $_COOKIE,
            protocol: $_SERVER['SERVER_PROTOCOL'] ?? '1.1',
            rawBody: $rawBody
        );
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    private static function gatherHeaders(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (!str_starts_with($key, 'HTTP_')) {
                continue;
            }

            $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
            $headers[$name] = (string) $value;
        }

        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['Content-Type'] = (string) $_SERVER['CONTENT_TYPE'];
        }

        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['Content-Length'] = (string) $_SERVER['CONTENT_LENGTH'];
        }

        return $headers;
    }

    /**
     * @param array<string, string> $headers
     */
    private static function isJson(array $headers): bool
    {
        $contentType = $headers['Content-Type'] ?? '';

        return str_contains($contentType, 'application/json');
    }
}
