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
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly array $methods,
        public readonly string $pattern,
        public readonly string $regex,
        public readonly array $parameterNames,
        public readonly Closure $handler,
        public readonly array $metadata = []
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function metadataFor(string $method): array
    {
        $metadata = $this->metadata;
        $methodKey = strtoupper($method);
        $methodSpecific = $metadata['methods'][$methodKey] ?? [];

        unset($metadata['methods']);

        if ($methodSpecific === []) {
            return $metadata;
        }

        return $this->mergeMetadata($metadata, $methodSpecific);
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $override
     * @return array<string, mixed>
     */
    private function mergeMetadata(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if ($key === 'tags' && isset($base['tags'], $value) && is_array($base['tags']) && is_array($value)) {
                $base['tags'] = $value;
                continue;
            }

            if ($key === 'responses' && isset($base['responses'], $value) && is_array($base['responses']) && is_array($value)) {
                $base['responses'] = array_replace($base['responses'], $value);
                continue;
            }

            if ($key === 'request' && isset($base['request'], $value) && is_array($base['request']) && is_array($value)) {
                $base['request'] = array_replace($base['request'], $value);
                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }
}
