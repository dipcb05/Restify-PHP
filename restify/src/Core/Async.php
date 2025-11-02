<?php

declare(strict_types=1);

namespace Restify\Core;

use Restify\Http\Response;
use RuntimeException;

final class Async
{
    private static ?self $instance = null;
    private EventLoop $loop;
    private HttpAsync $http;
    private bool $running = false;

    private function __construct()
    {
        $this->loop = new EventLoop();
        $this->http = new HttpAsync($this->loop);
        $this->loop->setHttp($this->http);
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public static function supportsFibers(): bool
    {
        return PHP_VERSION_ID >= 80100 && class_exists(\Fiber::class);
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    public function run(callable $callback): mixed
    {
        if (!self::supportsFibers()) {
            return $callback();
        }

        if ($this->running) {
            return $callback();
        }

        $this->running = true;
        $task = new Task(static fn () => $callback());

        $this->loop->enqueue($task, null, true);

        try {
            $this->loop->run($task);
            return $task->getResult();
        } finally {
            $this->running = false;
        }
    }

    public function parallel(array $callbacks): array
    {
        if (!self::supportsFibers() || !$this->running) {
            $results = [];

            foreach ($callbacks as $key => $callback) {
                $results[$key] = $callback();
            }

            return $results;
        }

        if ($callbacks === []) {
            return [];
        }

        $tasks = [];

        foreach ($callbacks as $key => $callback) {
            $task = new Task(static fn () => $callback());
            $tasks[$key] = $task;
            $this->loop->enqueue($task, null, true);
        }

        $results = [];

        foreach ($tasks as $key => $task) {
            $results[$key] = Task::await($task, $this->loop);
        }

        return $results;
    }

    public function http(string|array $request, array $options = []): array
    {
        if (!self::supportsFibers() || !$this->running) {
            return HttpAsync::sync($request, $options);
        }

        $task = Task::current();

        if ($task === null) {
            return HttpAsync::sync($request, $options);
        }

        return $this->http->request($request, $options, $task);
    }

    public function socket(string $host, int $port, string $payload = '', float $timeout = 5.0): string
    {
        if (!self::supportsFibers() || !$this->running) {
            return $this->blockingSocket($host, $port, $payload, $timeout);
        }

        $task = Task::current();

        if ($task === null) {
            return $this->blockingSocket($host, $port, $payload, $timeout);
        }

        $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);

        if (!is_resource($connection)) {
            throw new RuntimeException($errstr ?: 'Unable to open socket.');
        }

        if ($payload !== '') {
            fwrite($connection, $payload);
        }

        $this->loop->watchStream(
            $connection,
            function ($stream, EventLoop $loop) use ($task): void {
                $data = stream_get_contents($stream) ?: '';
                fclose($stream);
                $loop->enqueue($task, $data);
            },
            function ($stream, EventLoop $loop) use ($task): void {
                fclose($stream);
                $loop->enqueue($task, null, false, new RuntimeException('Socket read timed out.'));
            },
            $timeout
        );

        return Task::suspend();
    }

    public function background(string $script, array $arguments = []): void
    {
        $bin = PHP_BINARY;
        $path = $this->resolveScript($script);
        $args = implode(' ', array_map('escapeshellarg', $arguments));
        $command = sprintf('%s %s %s > /dev/null 2>&1 &', escapeshellarg($bin), escapeshellarg($path), $args);
        exec($command);
    }

    public function json(array $data, int $status = 200, array $meta = [], ?string $message = null): Response
    {
        return Response::json($data, $status, $meta, $message);
    }

    private function blockingSocket(string $host, int $port, string $payload, float $timeout): string
    {
        $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);

        if (!is_resource($connection)) {
            throw new RuntimeException($errstr ?: 'Unable to open socket.');
        }

        stream_set_timeout(
            $connection,
            (int) $timeout,
            (int) (($timeout - (int) $timeout) * 1_000_000)
        );

        if ($payload !== '') {
            fwrite($connection, $payload);
        }

        stream_set_blocking($connection, true);

        $contents = stream_get_contents($connection) ?: '';
        fclose($connection);

        return $contents;
    }

    private function resolveScript(string $script): string
    {
        if (str_starts_with($script, DIRECTORY_SEPARATOR)) {
            return $script;
        }

        $base = defined('RESTIFY_ROOT_PATH') ? RESTIFY_ROOT_PATH : RESTIFY_BASE_PATH;
        $candidate = $base . DIRECTORY_SEPARATOR . ltrim($script, DIRECTORY_SEPARATOR);

        if (!file_exists($candidate)) {
            throw new RuntimeException("Unable to locate background script: {$script}");
        }

        return $candidate;
    }
}
