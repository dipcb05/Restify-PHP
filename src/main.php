<?php

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use Dipcb05\RestifyPhp\Database;
use Dipcb05\RestifyPhp\Router;
use Dipcb05\RestifyPhp\Utils;

$dotenv = Dotenv::createImmutable(__DIR__ . '/config');
$dotenv->load();

Utils::logRequest();

$db = new Database();
$connection = $db->connect();

$router = new Router();
$endpointFile = $_ENV['ENDPOINT_FILE'] ?? 'api.php';
$endpointPath = __DIR__ . "/routes/{$endpointFile}";

if (file_exists($endpointPath)) {
    require_once $endpointPath;
} else {
    http_response_code(500);
    echo json_encode(["error" => "API Endpoint file not found!"]);
    exit;
}

$router->dispatch();
