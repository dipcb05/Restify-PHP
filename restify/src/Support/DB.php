<?php

declare(strict_types=1);

namespace Restify\Support;

use MongoDB\Client as MongoClient;
use PDO;
use PDOException;
use RuntimeException;

final class DB
{
    /**
     * @var array<string, mixed>
     */
    private static array $connections = [];

    private function __construct()
    {
    }

    public static function connection(?string $name = null): mixed
    {
        $name ??= getenv('DB_CONNECTION') ?: 'mysql';

        if (isset(self::$connections[$name])) {
            return self::$connections[$name];
        }

        return self::$connections[$name] = self::createConnection($name);
    }

    public static function disconnect(?string $name = null): void
    {
        if ($name === null) {
            self::$connections = [];

            return;
        }

        unset(self::$connections[$name]);
    }

    private static function createConnection(string $driver): mixed
    {
        return match ($driver) {
            'mysql', 'mariadb' => self::pdoConnection(
                driver: 'mysql',
                host: self::env("DB_{$driver}_HOST") ?? self::env('DB_HOST', '127.0.0.1'),
                port: self::env("DB_{$driver}_PORT") ?? self::env('DB_PORT', '3306'),
                database: self::env("DB_{$driver}_DATABASE") ?? self::env('DB_DATABASE', ''),
                username: self::env("DB_{$driver}_USERNAME") ?? self::env('DB_USERNAME', 'root'),
                password: self::env("DB_{$driver}_PASSWORD") ?? self::env('DB_PASSWORD')
            ),
            'pgsql', 'postgres', 'postgresql' => self::pdoConnection(
                driver: 'pgsql',
                host: self::env('DB_PGSQL_HOST') ?? self::env('DB_HOST', '127.0.0.1'),
                port: self::env('DB_PGSQL_PORT') ?? self::env('DB_PORT', '5432'),
                database: self::env('DB_PGSQL_DATABASE') ?? self::env('DB_DATABASE', ''),
                username: self::env('DB_PGSQL_USERNAME') ?? self::env('DB_USERNAME', 'postgres'),
                password: self::env('DB_PGSQL_PASSWORD') ?? self::env('DB_PASSWORD')
            ),
            'sqlsrv', 'mssql' => self::pdoConnection(
                driver: 'sqlsrv',
                host: self::env('DB_SQLSRV_HOST') ?? self::env('DB_HOST', '127.0.0.1'),
                port: self::env('DB_SQLSRV_PORT') ?? self::env('DB_PORT', '1433'),
                database: self::env('DB_SQLSRV_DATABASE') ?? self::env('DB_DATABASE', ''),
                username: self::env('DB_SQLSRV_USERNAME') ?? self::env('DB_USERNAME', 'sa'),
                password: self::env('DB_SQLSRV_PASSWORD') ?? self::env('DB_PASSWORD')
            ),
            'oci', 'oracle' => self::pdoConnection(
                driver: 'oci',
                host: self::env('DB_ORACLE_HOST') ?? self::env('DB_HOST', '127.0.0.1'),
                port: self::env('DB_ORACLE_PORT') ?? self::env('DB_PORT', '1521'),
                database: self::env('DB_ORACLE_SERVICE') ?? self::env('DB_DATABASE', 'xe'),
                username: self::env('DB_ORACLE_USERNAME') ?? self::env('DB_USERNAME', 'system'),
                password: self::env('DB_ORACLE_PASSWORD') ?? self::env('DB_PASSWORD')
            ),
            'sqlite' => self::pdoConnection(
                driver: 'sqlite',
                host: '',
                port: '',
                database: self::env('DB_SQLITE_PATH') ?? self::env('DB_DATABASE', ':memory:'),
                username: null,
                password: null
            ),
            'mongodb', 'mongo' => self::mongoConnection(
                uri: self::env('DB_MONGO_URI') ?? self::mongoUriFromEnv()
            ),
            default => throw new RuntimeException("Unsupported database driver: {$driver}")
        };
    }

    private static function pdoConnection(
        string $driver,
        ?string $host,
        ?string $port,
        string $database,
        ?string $username,
        ?string $password
    ): PDO {
        if (!class_exists(PDO::class)) {
            throw new RuntimeException('PDO extension is required for relational database connections.');
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        $dsn = self::buildDsn($driver, $host, $port, $database);

        try {
            return new PDO($dsn, $username, $password, $options);
        } catch (PDOException $exception) {
            throw new RuntimeException("Unable to connect to {$driver}: " . $exception->getMessage(), 0, $exception);
        }
    }

    private static function buildDsn(string $driver, ?string $host, ?string $port, string $database): string
    {
        return match ($driver) {
            'sqlite' => $driver . ':' . $database,
            'oci' => sprintf('oci:dbname=//%s:%s/%s', $host, $port, $database),
            'sqlsrv' => sprintf('sqlsrv:Server=%s,%s;Database=%s', $host, $port, $database),
            default => sprintf('%s:host=%s;%sdbname=%s', $driver, $host, $port ? "port={$port};" : '', $database),
        };
    }

    private static function mongoConnection(string $uri): MongoClient
    {
        if (!class_exists(MongoClient::class)) {
            throw new RuntimeException('MongoDB extension is required for Mongo connections.');
        }

        return new MongoClient($uri);
    }

    private static function mongoUriFromEnv(): string
    {
        $host = self::env('DB_MONGO_HOST', '127.0.0.1');
        $port = self::env('DB_MONGO_PORT', '27017');
        $username = self::env('DB_MONGO_USERNAME');
        $password = self::env('DB_MONGO_PASSWORD');
        $database = self::env('DB_MONGO_DATABASE', '');

        $credentials = '';
        if ($username !== null && $username !== '') {
            $credentials = $username;

            if ($password !== null && $password !== '') {
                $credentials .= ':' . $password;
            }

            $credentials .= '@';
        }

        $suffix = $database !== '' ? '/' . $database : '';

        return sprintf('mongodb://%s%s:%s%s', $credentials, $host, $port, $suffix);
    }

    private static function env(string $key, ?string $default = null): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false) {
            return $default;
        }

        return $value ?? $default;
    }
}
