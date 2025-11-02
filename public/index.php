<?php

declare(strict_types=1);

$app = require __DIR__ . '/../restify/bootstrap/app.php';

$response = $app->handle();

$response->send();
