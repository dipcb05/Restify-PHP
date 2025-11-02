<?php

declare(strict_types=1);

namespace Restify\Support;

use PDO;
use Restify\Http\Request;
use Restify\Http\Response;

final class Logging
{
    private function __construct()
    {
    }

    public static function record(Request $request, Response $response): void
    {
        $pdo = DB::connection();

        if (!$pdo instanceof PDO) {
            return;
        }

        $statement = $pdo->prepare(
            'INSERT INTO restify_logs (endpoint, request_method, user_data, status_code) VALUES (:endpoint, :method, :user, :status)'
        );

        $statement->execute([
            'endpoint' => $request->uri,
            'method' => $request->method,
            'user' => json_encode(self::userData($request), JSON_THROW_ON_ERROR),
            'status' => $response->status,
        ]);
    }

    private static function userData(Request $request): array
    {
        $headers = $request->headers;

        return [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $headers['User-Agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? null),
            'accept' => $headers['Accept'] ?? ($_SERVER['HTTP_ACCEPT'] ?? null),
            'referer' => $headers['Referer'] ?? ($_SERVER['HTTP_REFERER'] ?? null),
        ];
    }
}
