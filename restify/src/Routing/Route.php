<?php

declare(strict_types=1);

namespace Restify\Routing;

use Closure;

/**
 * Internal representation of a registered route.
 */
final class Route
{
    /**
     * @param array<string> $methods
     * @param array<string> $parameterNames
     */
    public function __construct(
        public readonly array $methods,
        public readonly string $pattern,
        public readonly string $regex,
        public readonly array $parameterNames,
        public readonly Closure $handler
    ) {
    }
}
