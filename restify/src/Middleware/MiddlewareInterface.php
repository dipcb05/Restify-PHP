<?php

declare(strict_types=1);

namespace Restify\Middleware;

use Restify\Http\Request;
use Restify\Http\Response;

interface MiddlewareInterface
{
    public function process(Request $request, callable $next): Response;
}
