<?php

declare(strict_types=1);

namespace Restify\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class Cache
{
    private function __construct()
    {
    }

    public static function has(string $key): bool
    {
        return self::apcuEnabled() ? apcu_exists($key) : false;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (self::apcuEnabled()) {
            $success = false;
            $value = apcu_fetch($key, $success);

            if ($success) {
                return $value;
            }
        }

        return $default;
    }

    public static function put(string $key, mixed $value, int $seconds = 0): void
    {
        if (!self::apcuEnabled()) {
            return;
        }

        apcu_store($key, $value, max(0, $seconds));
    }

    public static function remember(string $key, callable $callback, int $seconds = 0): mixed
    {
        if (self::apcuEnabled()) {
            $success = false;
            $value = apcu_fetch($key, $success);

            if ($success) {
                return $value;
            }
        }

        $result = $callback();
        self::put($key, $result, $seconds);

        return $result;
    }

    public static function delete(string $key): void
    {
        if (self::apcuEnabled()) {
            apcu_delete($key);
        }
    }

    public static function clear(): void
    {
        if (self::apcuEnabled()) {
            apcu_clear_cache();
        }
    }

    public static function primeOpcode(string $directory): void
    {
        if (!self::opcacheEnabled()) {
            return;
        }

        foreach (self::phpFiles($directory) as $file) {
            @opcache_compile_file($file);
        }
    }

    public static function flushOpcode(): void
    {
        if (self::opcacheEnabled()) {
            @opcache_reset();
        }
    }

    public static function rateLimit(string $key, int $limit, int $seconds): bool
    {
        if (!self::apcuEnabled()) {
            return true;
        }

        $token = 'rate:' . $key;
        $success = false;
        $count = apcu_fetch($token, $success);

        if (!$success) {
            apcu_store($token, 1, $seconds);

            return true;
        }

        if ($count >= $limit) {
            return false;
        }

        apcu_store($token, $count + 1, $seconds);

        return true;
    }

    private static function phpFiles(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private static function apcuEnabled(): bool
    {
        return function_exists('apcu_fetch')
            && ini_get('apc.enabled')
            && (PHP_SAPI !== 'cli' || ini_get('apc.enable_cli'));
    }

    private static function opcacheEnabled(): bool
    {
        return function_exists('opcache_compile_file') && ini_get('opcache.enable');
    }
}

