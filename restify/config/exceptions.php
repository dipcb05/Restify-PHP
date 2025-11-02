<?php

declare(strict_types=1);

$bool = static function (?string $value, bool $default = false): bool {
    if ($value === null || $value === '') {
        return $default;
    }

    return in_array(strtolower($value), ['1', 'true', 'on', 'yes', 'enabled'], true);
};

return [
    'enabled' => $bool($_ENV['EXCEPTIONS_ENABLED'] ?? null, true),
    'report' => $bool($_ENV['EXCEPTIONS_REPORT'] ?? null, true),
    'debug' => $bool($_ENV['APP_DEBUG'] ?? null, false),
    'trace' => $bool($_ENV['EXCEPTIONS_TRACE'] ?? null, false),
    'log_level' => strtolower($_ENV['EXCEPTIONS_LOG_LEVEL'] ?? 'error'),
];
