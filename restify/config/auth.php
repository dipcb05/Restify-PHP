<?php

declare(strict_types=1);

$bool = static function (?string $value, bool $default = true): bool {
    if ($value === null || $value === '') {
        return $default;
    }

    return in_array(strtolower($value), ['1', 'true', 'on', 'yes', 'enabled'], true);
};

$csv = static function (?string $value): array {
    if ($value === null || trim($value) === '') {
        return [];
    }

    return array_values(array_filter(array_map(
        static fn (string $part): string => '/' . ltrim(trim($part), '/'),
        explode(',', $value)
    )));
};

return [
    'enabled' => $bool($_ENV['AUTH_ENABLED'] ?? null, true),
    'header' => $_ENV['AUTH_HEADER'] ?? 'Authorization',
    'public_paths' => $csv($_ENV['AUTH_PUBLIC_PATHS'] ?? null),
    'schemes' => [
        'basic',
        'bearer',
    ],
];
