<?php

declare(strict_types=1);

namespace Restify\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Route
{
    /**
     * @var array<int, string>
     */
    public readonly array $methods;

    public function __construct(array|string $methods, public readonly string $path)
    {
        $methods = is_array($methods) ? $methods : [$methods];

        $normalised = array_map(
            static fn (string $method): string => strtoupper(trim($method)),
            $methods
        );

        $this->methods = array_values(array_unique($normalised));
    }
}
