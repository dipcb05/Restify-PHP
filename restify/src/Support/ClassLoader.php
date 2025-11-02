<?php

declare(strict_types=1);

namespace Restify\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ClassLoader
{
    private static bool $registered = false;

    private function __construct()
    {
    }

    public static function register(string $rootPath, ?string $frameworkPath = null): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;

        $frameworkPath ??= $rootPath . DIRECTORY_SEPARATOR . 'restify';

        $restifyRoot = $frameworkPath . DIRECTORY_SEPARATOR . 'src';
        $supportRoot = $frameworkPath . DIRECTORY_SEPARATOR . 'support';
        $packagesRoot = $frameworkPath . DIRECTORY_SEPARATOR . 'packages';
        $classRoot = $rootPath . DIRECTORY_SEPARATOR . 'class';

        spl_autoload_register(
            static function (string $class) use ($restifyRoot, $supportRoot, $packagesRoot, $classRoot): void {
                if (str_starts_with($class, 'Restify\\')) {
                    $relative = substr($class, strlen('Restify\\'));
                    $path = str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
                    $file = $restifyRoot . DIRECTORY_SEPARATOR . $path;

                    if (is_file($file)) {
                        require_once $file;

                        return;
                    }

                    $supportFile = $supportRoot . DIRECTORY_SEPARATOR . $path;

                    if (is_file($supportFile)) {
                        require_once $supportFile;
                    }

                    return;
                }

                $packageFile = $packagesRoot . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';
                if (is_file($packageFile)) {
                    require_once $packageFile;

                    return;
                }

                if (str_starts_with($class, 'App\\')) {
                    $relative = substr($class, 4);
                    $file = $classRoot . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

                    if (is_file($file)) {
                        require_once $file;
                    }

                    return;
                }

                $fallback = $classRoot . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';
                if (is_file($fallback)) {
                    require_once $fallback;
                }
            }
        );

        self::preloadUserClasses($classRoot);
        self::bootstrapPackages($packagesRoot);
    }

    private static function preloadUserClasses(string $classRoot): void
    {
        if (!is_dir($classRoot)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($classRoot, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                require_once $file->getPathname();
            }
        }
    }

    private static function bootstrapPackages(string $packagesRoot): void
    {
        if (!is_dir($packagesRoot)) {
            return;
        }

        $directories = glob($packagesRoot . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);

        if (!is_array($directories)) {
            return;
        }

        foreach ($directories as $directory) {
            $bootstrap = $directory . DIRECTORY_SEPARATOR . 'bootstrap.php';

            if (is_file($bootstrap)) {
                require_once $bootstrap;
            }
        }
    }
}
