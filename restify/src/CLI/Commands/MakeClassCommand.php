<?php

declare(strict_types=1);

namespace Restify\CLI\Commands;

use Restify\CLI\CommandContract;

final class MakeClassCommand implements CommandContract
{
    public function __construct(private readonly string $rootPath)
    {
    }

    public function signature(): string
    {
        return 'make:class';
    }

    public function description(): string
    {
        return 'Generate a new class file in the class directory.';
    }

    public function usage(): string
    {
        return 'php restify-cli make:class App\\Domain\\Example';
    }

    /**
     * @param array<int, string> $arguments
     */
    public function handle(array $arguments): int
    {
        $name = $arguments[0] ?? null;

        if ($name === null) {
            fwrite(STDERR, 'Class name is required.' . PHP_EOL);

            return 1;
        }

        $segments = array_map(
            static fn (string $segment): string => str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $segment),
            explode('\\', $name)
        );

        $className = array_pop($segments);
        $relativePath = implode(DIRECTORY_SEPARATOR, $segments);
        $classPath = $this->rootPath . DIRECTORY_SEPARATOR . 'class';

        $directory = $classPath . ($relativePath !== '' ? DIRECTORY_SEPARATOR . $relativePath : '');

        if (!is_dir($directory) && !mkdir($directory, recursive: true)) {
            fwrite(STDERR, "Unable to create directory: {$directory}" . PHP_EOL);

            return 1;
        }

        $filePath = $directory . DIRECTORY_SEPARATOR . $className . '.php';

        if (is_file($filePath)) {
            fwrite(STDERR, "Class file already exists: {$filePath}" . PHP_EOL);

            return 1;
        }

        $namespace = $this->deriveNamespace($segments);

        $contents = <<<PHP
<?php

declare(strict_types=1);

{$namespace}

final class {$className}
{
    public function __construct()
    {
        //
    }
}

PHP;

        file_put_contents($filePath, $contents);

        echo "Created: {$filePath}" . PHP_EOL;

        return 0;
    }

    /**
     * @param array<int, string> $segments
     */
    private function deriveNamespace(array $segments): string
    {
        if ($segments === []) {
            return '';
        }

        $namespace = implode('\\', array_map(
            static fn (string $segment): string => trim($segment),
            $segments
        ));

        return "namespace App\\{$namespace};";
    }
}
