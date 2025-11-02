<?php

declare(strict_types=1);

namespace Restify\CLI;

use RuntimeException;

final class TestCommand implements CommandContract
{
    public function __construct(
        private readonly string $rootPath,
        private readonly string $frameworkPath
    ) {
    }

    public function signature(): string
    {
        return 'test';
    }

    public function description(): string
    {
        return 'Run the Restify test suites (auto-detects PHPUnit when available).';
    }

    public function usage(): string
    {
        return 'php restify-cli test [--phpunit|--native] [additional phpunit flags]';
    }

    public function handle(array $arguments): int
    {
        $runner = null;
        $passthrough = [];

        foreach ($arguments as $argument) {
            if ($argument === '--phpunit') {
                $runner = 'phpunit';
                continue;
            }

            if (in_array($argument, ['--native', '--builtin'], true)) {
                $runner = 'native';
                continue;
            }

            $passthrough[] = $argument;
        }

        $phpunit = $this->resolvePhpunitExecutable();

        if ($runner === null) {
            $runner = $phpunit !== null ? 'phpunit' : 'native';
        }

        if ($runner === 'phpunit') {
            if ($phpunit === null) {
                fwrite(STDERR, 'PHPUnit executable not found. Falling back to native test runner.' . PHP_EOL);

                return $this->runNative($passthrough);
            }

            return $this->runPhpunit($phpunit, $passthrough);
        }

        return $this->runNative($passthrough);
    }

    private function runPhpunit(string $executable, array $arguments): int
    {
        $command = $this->escapeArgument(PHP_BINARY) . ' ' . $this->escapeArgument($executable);

        if (!$this->hasConfigurationArgument($arguments)) {
            $configuration = $this->locatePhpunitConfiguration();

            if ($configuration !== null) {
                $arguments[] = '--configuration=' . $configuration;
            }
        }

        if ($arguments !== []) {
            $command .= ' ' . implode(' ', array_map([$this, 'escapeArgument'], $arguments));
        }

        return $this->passthru($command);
    }

    private function runNative(array $arguments): int
    {
        $script = $this->resolveNativeRunner();

        if (!is_file($script)) {
            throw new RuntimeException('Unable to locate test runner script.');
        }

        $command = $this->escapeArgument(PHP_BINARY) . ' ' . $this->escapeArgument($script);

        if ($arguments !== []) {
            $command .= ' ' . implode(' ', array_map([$this, 'escapeArgument'], $arguments));
        }

        return $this->passthru($command);
    }

    private function resolvePhpunitExecutable(): ?string
    {
        $candidates = [
            $this->rootPath . '/vendor/bin/phpunit',
            $this->rootPath . '/vendor/bin/phpunit.phar',
            $this->frameworkPath . '/vendor/bin/phpunit',
            $this->frameworkPath . '/vendor/bin/phpunit.phar',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function resolveNativeRunner(): string
    {
        $projectRunner = $this->rootPath . '/tests/run.php';

        if (is_file($projectRunner)) {
            return $projectRunner;
        }

        return $this->frameworkPath . '/tests/run.php';
    }

    private function hasConfigurationArgument(array $arguments): bool
    {
        foreach ($arguments as $argument) {
            if (str_starts_with($argument, '--configuration')) {
                return true;
            }
        }

        return false;
    }

    private function locatePhpunitConfiguration(): ?string
    {
        $candidates = [
            $this->rootPath . '/phpunit.xml',
            $this->rootPath . '/phpunit.xml.dist',
            $this->frameworkPath . '/phpunit.xml',
            $this->frameworkPath . '/phpunit.xml.dist',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function escapeArgument(string $argument): string
    {
        return escapeshellarg($argument);
    }

    private function passthru(string $command): int
    {
        $previous = getcwd();

        if ($previous !== false) {
            chdir($this->rootPath);
        }

        passthru($command, $exitCode);

        if ($previous !== false) {
            chdir($previous);
        }

        return (int) $exitCode;
    }
}
