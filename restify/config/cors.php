<?php

declare(strict_types=1);

$bool = static function (?string $value, bool $default = false): bool {
    if ($value === null || $value === '') {
        return $default;
    }

    return in_array(strtolower($value), ['1', 'true', 'on', 'yes', 'enabled'], true);
};

$csv = static function (?string $value, array $default): array {
    if ($value === null || trim($value) === '') {
        return $default;
    }

    return array_values(array_filter(array_map(
        static fn (string $entry): string => trim($entry),
        explode(',', $value)
    )));
};

return [
    'enabled' => $bool($_ENV['CORS_ENABLED'] ?? null, true),
    'allowed_origins' => $csv($_ENV['CORS_ALLOWED_ORIGINS'] ?? null, ['*']),
    'allowed_methods' => $csv(
        $_ENV['CORS_ALLOWED_METHODS'] ?? null,
        ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']
    ),
    'allowed_headers' => $csv(
        $_ENV['CORS_ALLOWED_HEADERS'] ?? null,
        ['Content-Type', 'Authorization', 'Accept', 'X-Requested-With']
    ),
    'exposed_headers' => $csv($_ENV['CORS_EXPOSED_HEADERS'] ?? null, []),
    'max_age' => (int) ($_ENV['CORS_MAX_AGE'] ?? 600),
    'supports_credentials' => $bool($_ENV['CORS_ALLOW_CREDENTIALS'] ?? null, false),
];
