<?php

declare(strict_types=1);

namespace Restify\Support;

final class SchemaRegistry
{
    /**
     * @var array<string, mixed>|null
     */
    private static ?array $schemas = null;

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        if (self::$schemas === null) {
            $loaded = Config::get('schemas', []);
            self::$schemas = is_array($loaded) ? $loaded : [];
        }

        return self::$schemas;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(string $name): ?array
    {
        $schemas = self::all();
        $schema = $schemas[$name] ?? null;

        return is_array($schema) ? $schema : null;
    }
}
