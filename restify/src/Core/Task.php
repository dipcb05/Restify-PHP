<?php

declare(strict_types=1);

namespace Restify\Core;

use RuntimeException;
use Throwable;

final class Task
{
    private mixed $fiber;
    private ?EventLoop $loop = null;
    private bool $started = false;
    private bool $finished = false;
    private mixed $result = null;
    private ?Throwable $error = null;
    /**
     * @var array<int, array{task: self, loop: EventLoop}>
     */
    private array $waiters = [];
    private static ?self $current = null;

    public function __construct(callable $callback)
    {
        if (!class_exists(\Fiber::class)) {
            throw new RuntimeException('Fibers are not available in this PHP build.');
        }

        $this->fiber = new \Fiber($callback);
    }

    public function setLoop(EventLoop $loop): void
    {
        $this->loop = $loop;
    }

    public function start(): void
    {
        if ($this->started) {
            return;
        }

        $this->started = true;
        $this->invoke(static fn () => $this->fiber->start());
    }

    public function resume(mixed $value = null): void
    {
        $this->assertStarted();
        $this->invoke(static fn () => $this->fiber->resume($value));
    }

    public function throw(Throwable $throwable): void
    {
        $this->assertStarted();
        $this->invoke(static fn () => $this->fiber->throw($throwable));
    }

    public function isFinished(): bool
    {
        return $this->finished;
    }

    public function getResult(): mixed
    {
        if ($this->error) {
            throw $this->error;
        }

        return $this->result;
    }

    public function addWaiter(self $task, EventLoop $loop): void
    {
        $this->waiters[] = [
            'task' => $task,
            'loop' => $loop,
        ];
    }

    public static function await(self $task, EventLoop $loop): mixed
    {
        if ($task->isFinished()) {
            return $task->getResult();
        }

        $current = self::current();

        if ($current === null) {
            throw new RuntimeException('Await requires an active fiber context.');
        }

        $task->addWaiter($current, $loop);

        return self::suspend();
    }

    public static function suspend(mixed $value = null): mixed
    {
        return \Fiber::suspend($value);
    }

    public static function current(): ?self
    {
        return self::$current;
    }

    private function invoke(callable $operation): void
    {
        self::$current = $this;

        try {
            $operation();

            if ($this->fiber->isTerminated()) {
                $this->complete();
            }
        } catch (Throwable $throwable) {
            $this->complete($throwable);
            throw $throwable;
        } finally {
            self::$current = null;
        }
    }

    private function complete(?Throwable $throwable = null): void
    {
        $this->finished = true;

        if ($throwable) {
            $this->error = $throwable;
            $this->notifyWaitersWithError($throwable);
            return;
        }

        $this->result = $this->fiber->getReturn();
        $this->notifyWaitersWithValue($this->result);
    }

    private function notifyWaitersWithError(Throwable $throwable): void
    {
        foreach ($this->waiters as $waiter) {
            $waiter['loop']->enqueue($waiter['task'], null, false, $throwable);
        }

        $this->waiters = [];
    }

    private function notifyWaitersWithValue(mixed $value): void
    {
        foreach ($this->waiters as $waiter) {
            $waiter['loop']->enqueue($waiter['task'], $value);
        }

        $this->waiters = [];
    }

    private function assertStarted(): void
    {
        if (!$this->started) {
            throw new RuntimeException('Task has not been started.');
        }
    }
}
