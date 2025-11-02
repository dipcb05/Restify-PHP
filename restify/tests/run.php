<?php

declare(strict_types=1);

use Restify\Testing\TestRunner;

$paths = require __DIR__ . '/bootstrap.php';

$frameworkPath = $paths['frameworkPath'] ?? dirname(__DIR__);
$rootPath = $paths['rootPath'] ?? dirname(__DIR__, 2);

$directories = [
    __DIR__,
];

$projectTests = $rootPath . '/tests';

if (is_dir($projectTests) && realpath($projectTests) !== realpath(__DIR__)) {
    $directories[] = $projectTests;
}

$tests = [];
$loaded = [];

foreach ($directories as $directory) {
    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile() || !str_ends_with($file->getFilename(), 'Test.php')) {
            continue;
        }

        $path = $file->getPathname();

        if (isset($loaded[$path])) {
            continue;
        }

        require_once $path;
        $loaded[$path] = true;

        $class = basename($path, '.php');
        $tests[] = 'Tests\\' . $class;
    }
}

$runner = new TestRunner($tests);

exit($runner->run());
