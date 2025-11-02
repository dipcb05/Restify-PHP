<?php

declare(strict_types=1);

use Restify\Support\ClassLoader;
use Restify\Support\Env;
use Restify\Testing\TestRunner;

$frameworkPath = dirname(__DIR__);
$rootPath = dirname(__DIR__, 2);

require $frameworkPath . '/src/Support/ClassLoader.php';

ClassLoader::register($rootPath, $frameworkPath);
Env::load($rootPath);

$tests = [];

foreach (glob(__DIR__ . '/*Test.php') as $file) {
    require_once $file;

    $class = basename($file, '.php');
    $tests[] = 'Tests\\' . $class;
}

$runner = new TestRunner($tests);

exit($runner->run());
