<?php

namespace Dipcb05\RestifyPhp;

use PDO;
use Dotenv\Dotenv;

class Utils
{
    private PDO $db;
    private bool $logFull;
    private bool $logSuccess;
    private bool $logFailed;
    private string $logTable;

    public function __construct(PDO $db)
    {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../config');
        $dotenv->load();
        
        $this->db = $db;
        $this->logFull = getenv('API_FULL_LOG_STORE') === 'TRUE';
        $this->logSuccess = getenv('API_ONLY_SUCCESS_LOG_STORE') === 'TRUE';
        $this->logFailed = getenv('API_ONLY_FAILED_LOG_STORE') === 'TRUE';
        $this->logTable = getenv('LOG_TABLE') ?: 'api_logs';
    }

    public static function logRequest(Request $request, array $response, int $statusCode): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
        $method = $request->getMethod();
        $uri = $request->getUri();
        $headers = json_encode($request->getHeaders());
        $payload = json_encode($request->getBodyParams());
        $status = ($statusCode >= 200 && $statusCode < 300) ? 'success' : 'error';
        $timestamp = date('Y-m-d H:i:s');
        $responseBody = json_encode($response);

        if ($this->shouldLog($status)) {
            $stmt = $this->db->prepare("INSERT INTO {$this->logTable} (ip, user_agent, method, uri, headers, payload, status, status_code, response, timestamp) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$ip, $userAgent, $method, $uri, $headers, $payload, $status, $statusCode, $responseBody, $timestamp]);
        }
    }

    private static function shouldLog(string $status): bool
    {
        if ($this->logFull) return true;
        if ($this->logSuccess && $status === 'success') return true;
        if ($this->logFailed && $status === 'error') return true;
        return false;
    }
}
