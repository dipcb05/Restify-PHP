<?php

declare(strict_types=1);

namespace Restify\CLI;

use Restify\Support\Async as AsyncSupport;

final class AsyncCommand implements CommandContract
{
    public function __construct(private readonly string $rootPath)
    {
    }

    public function signature(): string
    {
        return 'run';
    }

    public function description(): string
    {
        return 'Serve the application with optional async support.';
    }

    public function usage(): string
    {
        return 'php restify-cli run [--host HOST] [--port PORT] [--docroot PATH] [--async] [router]';
    }

    public function handle(array $arguments): int
    {
        $host = '127.0.0.1';
        $port = '8000';
        $docroot = 'public';
        $asyncFlag = false;
        $filtered = [];
        $count = count($arguments);

        for ($i = 0; $i < $count; $i++) {
            $argument = $arguments[$i];

            if ($argument === '--async') {
                $asyncFlag = true;
                continue;
            }

            if ($argument === '--host' && isset($arguments[$i + 1])) {
                $host = $arguments[++$i];
                continue;
            }

            if (str_starts_with($argument, '--host=')) {
                $host = substr($argument, 7);
                continue;
            }

            if ($argument === '--port' && isset($arguments[$i + 1])) {
                $port = $arguments[++$i];
                continue;
            }

            if (str_starts_with($argument, '--port=')) {
                $port = substr($argument, 7);
                continue;
            }

            if ($argument === '--docroot' && isset($arguments[$i + 1])) {
                $docroot = $arguments[++$i];
                continue;
            }

            if (str_starts_with($argument, '--docroot=')) {
                $docroot = substr($argument, 10);
                continue;
            }

            $filtered[] = $argument;
        }

        $docrootPath = $this->resolveDocroot($docroot);
        $supportsAsync = AsyncSupport::isSupported();

        if ($asyncFlag && !$supportsAsync) {
            fwrite(STDERR, 'Async engine unavailable; continuing in sync mode.' . PHP_EOL);
        }

        $enabled = $asyncFlag && $supportsAsync ? '1' : '0';
        putenv("RESTIFY_ASYNC={$enabled}");
        $_ENV['RESTIFY_ASYNC'] = $enabled;

        $command = sprintf(
            '%s -S %s:%s -t %s %s',
            escapeshellarg(PHP_BINARY),
            $host,
            $port,
            escapeshellarg($docrootPath),
            implode(' ', array_map('escapeshellarg', $filtered))
        );

        passthru($command);

        return 0;
    }

    private function resolveDocroot(string $path): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return $this->rootPath . DIRECTORY_SEPARATOR . $path;
    }
}
