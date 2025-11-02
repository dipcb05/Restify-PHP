<?php

declare(strict_types=1);

namespace Restify\Testing;

use Throwable;

final class TestRunner
{
    /**
     * @param array<int, class-string<TestCase>> $tests
     */
    public function __construct(private readonly array $tests)
    {
    }

    public function run(): int
    {
        $failures = 0;

        foreach ($this->tests as $testClass) {
            $test = new $testClass();

            foreach ($this->discoverTestMethods($test) as $method) {
                $test->boot();

                try {
                    $test->{$method}();
                    $this->output('.', false);
                } catch (Throwable $throwable) {
                    $failures++;
                    $this->output('F', false);
                    $this->reportFailure($testClass, $method, $throwable);
                } finally {
                    $test->shutdown();
                }
            }
        }

        $this->output(PHP_EOL . ($failures === 0 ? 'All tests passed.' : "{$failures} test(s) failed."));

        return $failures === 0 ? 0 : 1;
    }

    /**
     * @return array<int, string>
     */
    private function discoverTestMethods(TestCase $test): array
    {
        $methods = get_class_methods($test);

        return array_values(
            array_filter(
                $methods,
                static fn (string $method): bool => str_starts_with($method, 'test')
            )
        );
    }

    private function reportFailure(string $class, string $method, Throwable $throwable): void
    {
        $this->output(
            sprintf(
                PHP_EOL . 'Failure: %s::%s%sReason: %s',
                $class,
                $method,
                PHP_EOL,
                $throwable->getMessage()
            )
        );
    }

    private function output(string $message, bool $newline = true): void
    {
        echo $message;

        if ($newline) {
            echo PHP_EOL;
        }
    }
}
