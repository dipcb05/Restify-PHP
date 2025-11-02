<?php

declare(strict_types=1);

use Restify\Core\Application;
use Restify\Support\ClassLoader;
use Restify\Support\Env;

defined('RESTIFY_START') || define('RESTIFY_START', microtime(true));
defined('RESTIFY_BASE_PATH') || define('RESTIFY_BASE_PATH', dirname(__DIR__));
defined('RESTIFY_ROOT_PATH') || define('RESTIFY_ROOT_PATH', dirname(__DIR__, 2));

require_once RESTIFY_BASE_PATH . '/src/Support/ClassLoader.php';

ClassLoader::register(RESTIFY_ROOT_PATH, RESTIFY_BASE_PATH);

$env = Env::load(RESTIFY_ROOT_PATH);

date_default_timezone_set($env['APP_TIMEZONE'] ?? 'UTC');

return new Application(
    basePath: RESTIFY_BASE_PATH,
    rootPath: RESTIFY_ROOT_PATH,
    environment: $env
);
