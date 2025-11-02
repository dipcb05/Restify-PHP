<?php

declare(strict_types=1);

use Restify\Support\ClassLoader;
use Restify\Support\Env;

$frameworkPath = dirname(__DIR__);
$rootPath = dirname(__DIR__, 2);

require_once $frameworkPath . '/src/Support/ClassLoader.php';

ClassLoader::register($rootPath, $frameworkPath);
Env::load($rootPath);

return [
    'frameworkPath' => $frameworkPath,
    'rootPath' => $rootPath,
];
