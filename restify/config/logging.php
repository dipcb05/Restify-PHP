<?php

declare(strict_types=1);

$bool = static function (?string $value, bool $default = false): bool {
    if ($value === null || $value === '') {
        return $default;
    }

    return in_array(strtolower($value), ['1', 'true', 'on', 'yes', 'enabled'], true);
};

$path = $_ENV['LOG_PATH'] ?? (RESTIFY_ROOT_PATH . '/storage/logs/restify.log');
$sensitive = $_ENV['LOG_SENSITIVE_FIELDS'] ?? 'password,token,secret,authorization';

return [
    'enabled' => $bool($_ENV['LOGGING_ENABLED'] ?? null, true),
    'level' => strtolower($_ENV['LOG_LEVEL'] ?? 'info'),
    'path' => $path,
    'request' => [
        'enabled' => $bool($_ENV['LOG_REQUEST_ENABLED'] ?? null, true),
        'headers' => $bool($_ENV['LOG_REQUEST_HEADERS'] ?? null, false),
        'body' => $bool($_ENV['LOG_REQUEST_BODY'] ?? null, true),
    ],
    'response' => [
        'enabled' => $bool($_ENV['LOG_RESPONSE_ENABLED'] ?? null, true),
        'headers' => $bool($_ENV['LOG_RESPONSE_HEADERS'] ?? null, false),
        'body' => $bool($_ENV['LOG_RESPONSE_BODY'] ?? null, false),
    ],
    'max_body_length' => (int) ($_ENV['LOG_BODY_LIMIT'] ?? 2048),
    'sensitive_fields' => array_values(array_filter(array_map(
        static fn (string $field): string => strtolower(trim($field)),
        explode(',', $sensitive)
    ))),
    'database' => [
        'enabled' => $bool($_ENV['LOG_DATABASE_ENABLED'] ?? null, true),
    ],
    'level_map' => [
        'info' => [200, 399],
        'warning' => [400, 499],
        'error' => [500, 599],
    ],
];
