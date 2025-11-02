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
    public readonly string $path;
    public readonly ?string $summary;
    public readonly ?string $description;
    /**
     * @var array<int, string>
     */
    public readonly array $tags;
    public readonly array|string|null $request;
    /**
     * @var array<string, mixed>
     */
    public readonly array $responses;
    /**
     * @var array<string, mixed>
     */
    public readonly array $extra;

    /**
     * @param array<int, string>|string $methods
     * @param array<int, string> $tags
     * @param array<string, mixed> $responses
     * @param array<string, mixed> $extra
     */
    public function __construct(
        array|string $methods,
        string $path,
        ?string $summary = null,
        ?string $description = null,
        array $tags = [],
        array|string|null $request = null,
        array $responses = [],
        array $extra = []
    ) {
        $methods = is_array($methods) ? $methods : [$methods];

        $normalised = array_map(
            static fn (string $method): string => strtoupper(trim($method)),
            $methods
        );

        $this->methods = array_values(array_unique($normalised));
        $this->path = $path;
        $this->summary = $summary;
        $this->description = $description;
        $this->tags = array_values(array_filter(array_map(
            static fn (string $tag): string => trim($tag),
            $tags
        ), static fn (string $tag): bool => $tag !== ''));
        $this->request = $request;
        $this->responses = $responses;
        $this->extra = $extra;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        $metadata = $this->extra;

        if ($this->summary !== null) {
            $metadata['summary'] = $this->summary;
        }

        if ($this->description !== null) {
            $metadata['description'] = $this->description;
        }

        if ($this->tags !== []) {
            $metadata['tags'] = $this->tags;
        }

        if ($this->request !== null) {
            $metadata['request'] = is_array($this->request)
                ? $this->request
                : ['schema' => $this->request];
        }

        if ($this->responses !== []) {
            $metadata['responses'] = $this->responses;
        }

        return $metadata;
    }
}
