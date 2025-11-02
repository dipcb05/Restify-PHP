<?php

declare(strict_types=1);

namespace Restify\Support;

use DateTimeImmutable;
use DateTimeInterface;
use RuntimeException;

final class Logger
{
    private const LEVELS = [
        'debug' => 100,
        'info' => 200,
        'notice' => 250,
        'warning' => 300,
        'error' => 400,
        'critical' => 500,
        'alert' => 550,
        'emergency' => 600,
    ];

    private function __construct()
    {
    }

    public static function log(string $level, string $message, array $context = [], ?array $config = null): void
    {
        $config ??= Config::get('logging', []);

        if (!(bool) ($config['enabled'] ?? true)) {
            return;
        }

        $level = strtolower($level);
        $threshold = strtolower((string) ($config['level'] ?? 'info'));

        if (!isset(self::LEVELS[$level], self::LEVELS[$threshold])) {
            return;
        }

        if (self::LEVELS[$level] < self::LEVELS[$threshold]) {
            return;
        }

        $path = $config['path'] ?? (RESTIFY_ROOT_PATH . '/storage/logs/restify.log');
        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, recursive: true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create log directory: ' . $directory);
        }

        $entry = [
            'timestamp' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];

        file_put_contents($path, json_encode($entry, JSON_THROW_ON_ERROR) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    public static function debug(string $message, array $context = []): void
    {
        self::log('debug', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('info', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('error', $message, $context);
    }
}
