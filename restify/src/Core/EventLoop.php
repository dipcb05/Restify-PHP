<?php

declare(strict_types=1);

namespace Restify\Core;

use Closure;
use SplQueue;
use Throwable;

final class EventLoop
{
    private SplQueue $queue;

    private array $streams = [];
    private ?HttpAsync $http = null;
    private bool $running = false;

    public function __construct()
    {
        $this->queue = new SplQueue();
    }

    public function setHttp(HttpAsync $http): void
    {
        $this->http = $http;
    }

    public function enqueue(Task $task, mixed $value = null, bool $start = false, ?Throwable $error = null): void
    {
        $this->queue->enqueue([
            'task' => $task,
            'value' => $value,
            'start' => $start,
            'error' => $error,
        ]);
    }

    public function run(Task $root): void
    {
        $this->running = true;

        try {
            while (true) {
                $worked = $this->drainQueue($root);
                $httpActivity = $this->http?->tick() ?? false;
                $streamActivity = $this->dispatchStreams();

                if ($root->isFinished() && !$this->hasWork()) {
                    break;
                }

                if (!$worked && !$httpActivity && !$streamActivity) {
                    usleep(5000);
                }
            }
        } finally {
            $this->running = false;
        }
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    public function hasWork(): bool
    {
        if (!$this->queue->isEmpty()) {
            return true;
        }

        if ($this->http && $this->http->hasPending()) {
            return true;
        }

        return $this->streams !== [];
    }

    public function watchStream(mixed $stream, Closure $onReadable, ?Closure $onTimeout = null, ?float $timeout = null): void
    {
        if (!is_resource($stream)) {
            throw new \RuntimeException('Invalid stream resource');
        }

        stream_set_blocking($stream, false);

        $id = (int) $stream;
        $expiresAt = $timeout ? microtime(true) + $timeout : null;

        $this->streams[$id] = [
            'stream' => $stream,
            'readable' => $onReadable,
            'timeout' => $onTimeout,
            'expires' => $expiresAt,
        ];
    }

    public function unwatchStream(mixed $stream): void
    {
        if (is_resource($stream)) {
            $id = (int) $stream;
            unset($this->streams[$id]);
        }
    }

    private function drainQueue(Task $root): bool
    {
        $worked = false;

        while (!$this->queue->isEmpty()) {
            $worked = true;
            $payload = $this->queue->dequeue();
            $task = $payload['task'];

            if ($task->isFinished()) {
                continue;
            }

            $task->setLoop($this);

            try {
                if ($payload['error'] instanceof Throwable) {
                    $task->throw($payload['error']);
                } elseif ($payload['start']) {
                    $task->start();
                } else {
                    $task->resume($payload['value']);
                }
            } catch (Throwable $exception) {
                if ($task === $root) {
                    throw $exception;
                }
            }
        }

        return $worked;
    }

    private function dispatchStreams(): bool
    {
        if ($this->streams === []) {
            return false;
        }

        $now = microtime(true);
        $activity = false;

        foreach ($this->streams as $id => $watcher) {
            if ($watcher['expires'] && $watcher['expires'] <= $now) {
                unset($this->streams[$id]);
                if ($watcher['timeout']) {
                    ($watcher['timeout'])($watcher['stream'], $this);
                    $activity = true;
                }
            }
        }

        $read = [];

        foreach ($this->streams as $watcher) {
            $read[] = $watcher['stream'];
        }

        if ($read === []) {
            return $activity;
        }

        $write = null;
        $except = null;

        $result = @stream_select($read, $write, $except, 0, 200000);

        if ($result === false || $result === 0) {
            return $activity;
        }

        foreach ($read as $resource) {
            $id = (int) $resource;

            if (!isset($this->streams[$id])) {
                continue;
            }

            $callback = $this->streams[$id]['readable'];
            unset($this->streams[$id]);
            $callback($resource, $this);
        }

        return true;
    }
}
