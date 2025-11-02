<?php

declare(strict_types=1);

namespace Restify\CLI;

use Restify\CLI\AsyncCommand;
use Restify\CLI\AuthenticationCommand;
use Restify\CLI\Commands\MakeClassCommand;
use Restify\CLI\DocsCommand;
use Restify\CLI\LogCommand;

/**
 * Minimal CLI entrypoint for repetitive scaffolding tasks.
 */
final class Console
{
    /**
     * @var array<string, CommandContract>
     */
    private array $commands = [];

    public function __construct(private readonly string $rootPath)
    {
        $this->register(new AsyncCommand($this->rootPath));
        $this->register(new MakeClassCommand($this->rootPath));
        $this->register(new LogCommand($this->rootPath));
        $this->register(new AuthenticationCommand($this->rootPath));
        $this->register(new DocsCommand($this->rootPath));
    }

    public function register(CommandContract $command): void
    {
        $this->commands[$command->signature()] = $command;
    }

    /**
     * @param array<int, string> $arguments
     */
    public function run(array $arguments): int
    {
        $tokens = array_slice($arguments, 1);

        if ($tokens === []) {
            $this->outputUsage();

            return 0;
        }

        $first = $tokens[0];

        if ($first === 'help') {
            $parts = array_slice($tokens, 1);

            if ($parts === []) {
                $this->outputUsage();

                return 0;
            }

            $payload = array_slice($parts, 1);
            $signature = $this->mapSignature($parts[0], $payload);

            if (!isset($this->commands[$signature])) {
                fwrite(STDERR, "Unknown command: {$parts[0]}" . PHP_EOL);
                $this->outputUsage();

                return 1;
            }

            $this->outputCommandUsage($signature);

            return 0;
        }

        if (in_array($first, ['--help', '-h', 'list'], true)) {
            $this->outputUsage();

            return 0;
        }

        $payload = array_slice($tokens, 1);
        $signature = $this->mapSignature($first, $payload);

        if (!isset($this->commands[$signature])) {
            fwrite(STDERR, "Unknown command: {$first}" . PHP_EOL);
            $this->outputUsage();

            return 1;
        }

        if ($this->payloadRequestsHelp($payload)) {
            $this->outputCommandUsage($signature);

            return 0;
        }

        return $this->commands[$signature]->handle($payload);
    }

    private function outputUsage(): void
    {
        echo 'Restify CLI' . PHP_EOL;
        echo 'Usage:' . PHP_EOL;
        echo '  php restify-cli [--help|-h]' . PHP_EOL;
        echo '  php restify-cli help <command>' . PHP_EOL;
        echo '  php restify-cli <command> [arguments]' . PHP_EOL;
        echo 'Commands:' . PHP_EOL;

        foreach ($this->commands as $signature => $command) {
            echo "  {$signature}\t{$command->description()}" . PHP_EOL;
        }
    }

    private function outputCommandUsage(string $signature): void
    {
        $command = $this->commands[$signature];
        echo "{$signature}" . PHP_EOL;
        echo $command->description() . PHP_EOL;

        $usage = $command->usage();

        if ($usage !== '') {
            echo 'Usage: ' . $usage . PHP_EOL;
        }
    }

    /**
     * @param array<int, string> $payload
     */
    private function mapSignature(string $signature, array &$payload): string
    {
        if ($signature === 'make' && $payload !== [] && $payload[0] === 'class') {
            array_shift($payload);

            return 'make:class';
        }

        return $signature;
    }

    /**
     * @param array<int, string> $payload
     */
    private function payloadRequestsHelp(array &$payload): bool
    {
        $requested = false;

        foreach ($payload as $index => $token) {
            if ($token === '--help' || $token === '-h') {
                unset($payload[$index]);
                $requested = true;
            }
        }

        if ($requested) {
            $payload = array_values($payload);
        }

        return $requested;
    }
}
