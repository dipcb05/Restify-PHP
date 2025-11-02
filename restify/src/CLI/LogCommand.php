<?php

declare(strict_types=1);

namespace Restify\CLI;

use PDO;
use Restify\Support\DB;
use Restify\Support\Schema;
use RuntimeException;

final class LogCommand implements CommandContract
{
    public function __construct(private readonly string $rootPath)
    {
    }

    public function signature(): string
    {
        return 'log';
    }

    public function description(): string
    {
        return 'Initialise database tables for request logging.';
    }

    public function usage(): string
    {
        return 'php restify-cli log';
    }

    public function handle(array $arguments): int
    {
        $connection = DB::connection();

        if (!$connection instanceof PDO) {
            throw new RuntimeException('Logging requires a PDO-enabled database connection.');
        }

        Schema::ensureLogsTable($connection);
        Schema::ensureTokensTable($connection);

        echo "Log and token tables are ready." . PHP_EOL;

        return 0;
    }
}
