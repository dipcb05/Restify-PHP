<?php

namespace Dipcb05\RestifyPhp;

class Request
{
    private array $queryParams;
    private array $bodyParams;
    private array $headers;
    private string $method;
    private string $uri;

    public function __construct()
    {
        $this->queryParams = $_GET;
        $this->bodyParams = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $this->headers = getallheaders();
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function getBodyParams(): array
    {
        return $this->bodyParams;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    public function get(string $key, $default = null)
    {
        return $this->queryParams[$key] ?? $this->bodyParams[$key] ?? $default;
    }
}
