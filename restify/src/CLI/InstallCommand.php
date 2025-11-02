<?php

declare(strict_types=1);

namespace Restify\CLI;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class InstallCommand implements CommandContract
{
    public function __construct(
        private readonly string $rootPath,
        private readonly string $frameworkPath,
        private readonly string $packagePath
    ) {
    }

    public function signature(): string
    {
        return 'install';
    }

    public function description(): string
    {
        return 'Publish the Restify skeleton into the current project.';
    }

    public function usage(): string
    {
        return 'php restify-cli install';
    }

    public function handle(array $arguments): int
    {
        $target = realpath($this->rootPath) ?: $this->rootPath;
        $source = realpath($this->packagePath) ?: $this->packagePath;

        if (strtolower($target) === strtolower($source)) {
            echo 'Project already contains the Restify skeleton.' . PHP_EOL;

            return 0;
        }

        $directories = ['restify', 'api', 'class', 'public', 'storage'];
        foreach ($directories as $directory) {
            $this->publishDirectory($source . DIRECTORY_SEPARATOR . $directory, $target . DIRECTORY_SEPARATOR . $directory);
        }

        $files = ['restify-cli', '.env.example', '.htaccess', 'README.md'];
        foreach ($files as $file) {
            $this->publishFile($source . DIRECTORY_SEPARATOR . $file, $target . DIRECTORY_SEPARATOR . $file);
        }

        echo 'Restify skeleton published successfully.' . PHP_EOL;

        return 0;
    }

    private function publishDirectory(string $source, string $destination): void
    {
        if (!is_dir($source)) {
            return;
        }

        if (is_dir($destination)) {
            return;
        }

        $this->recursiveCopy($source, $destination);
    }

    private function publishFile(string $source, string $destination): void
    {
        if (!is_file($source)) {
            return;
        }

        if (is_file($destination)) {
            return;
        }

        $destinationDir = dirname($destination);
        if (!is_dir($destinationDir) && !mkdir($destinationDir, recursive: true) && !is_dir($destinationDir)) {
            throw new RuntimeException('Unable to create directory: ' . $destinationDir);
        }

        copy($source, $destination);
    }

    private function recursiveCopy(string $source, string $destination): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $targetPath = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();

            if ($item->isDir()) {
                if (!is_dir($targetPath) && !mkdir($targetPath, recursive: true) && !is_dir($targetPath)) {
                    throw new RuntimeException('Unable to create directory: ' . $targetPath);
                }
            } else {
                copy($item->getPathname(), $targetPath);
            }
        }
    }
}
