<?php

declare(strict_types=1);

namespace Restify\Support;

final class Env
{
    private const DEFAULT_FILE = '.env';

    private function __construct()
    {
    }

    /**
     * @return array<string, string>
     */
    public static function load(string $basePath, ?string $file = null): array
    {
        $path = $basePath . DIRECTORY_SEPARATOR . ($file ?? self::DEFAULT_FILE);

        if (!is_file($path)) {
            return [];
        }

        $variables = [];

        foreach (file($path, FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            [$key, $value] = self::parseLine($line);

            $variables[$key] = $value;
            $_ENV[$key] = $value;
            $_SERVER[$key] ??= $value;
        }

        return $variables;
    }

    /**
     * @return array{string, string}
     */
    private static function parseLine(string $line): array
    {
        $parts = explode('=', $line, 2);
        $key = strtoupper(trim($parts[0]));
        $value = $parts[1] ?? '';
        $value = trim($value);

        if ($value !== '' && $value[0] === '"' && $value[-1] === '"') {
            $value = substr($value, 1, -1);
        }

        return [$key, $value];
    }
}
