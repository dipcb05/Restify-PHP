<?php

declare(strict_types=1);

namespace Restify\CLI;

interface CommandContract
{
    public function signature(): string;

    /**
     * @param array<int, string> $arguments
     */
    public function handle(array $arguments): int;

    public function description(): string;

    public function usage(): string;
}
