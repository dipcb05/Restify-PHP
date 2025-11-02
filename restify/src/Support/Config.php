<?php

declare(strict_types=1);

namespace Restify\Support;

final class Config
{
    /**
     * @var array<string, mixed>
     */
    private static array $cache = [];

    private function __construct()
    {
    }

    public static function get(string $name, mixed $default = null): mixed
    {
        if (isset(self::$cache[$name])) {
            return self::$cache[$name];
        }

        $path = RESTIFY_BASE_PATH . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . $name . '.php';

        if (!is_file($path)) {
            return $default;
        }

        /** @var mixed $config */
        $config = require $path;
        self::$cache[$name] = $config;

        return $config;
    }
}
