<?php

declare(strict_types=1);

namespace Restify\Http;

/**
 * Unified HTTP response representation with JSON helpers.
 */
final class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly string $content,
        public readonly int $status = 200,
        public readonly array $headers = []
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $meta
     */
    public static function json(
        array $data,
        int $status = 200,
        array $meta = [],
        ?string $message = null
    ): self {
        $payload = [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'message' => $message,
            'data' => $data,
            'meta' => $meta,
        ];

        return new self(
            content: json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            status: $status,
            headers: [
                'Content-Type' => 'application/json; charset=utf-8',
            ]
        );
    }

    public static function text(string $content, int $status = 200): self
    {
        return new self(
            content: $content,
            status: $status,
            headers: [
                'Content-Type' => 'text/plain; charset=utf-8',
            ]
        );
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value, replace: true);
        }

        echo $this->content;
    }
}
