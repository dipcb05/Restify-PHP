<?php

declare(strict_types=1);

namespace Restify\Core;

use RuntimeException;

final class HttpAsync
{
    private $multi;

    /**
     * @var array<int, \CurlHandle>
     */
    private array $handles = [];

    /**
     * @var array<int, Task>
     */
    private array $tasks = [];

    private EventLoop $loop;

    public function __construct(EventLoop $loop)
    {
        if (!function_exists('curl_multi_init')) {
            throw new RuntimeException('cURL extension is required for async HTTP.');
        }

        $this->multi = curl_multi_init();
        $this->loop = $loop;
    }

    public function __destruct()
    {
        foreach ($this->handles as $handle) {
            curl_multi_remove_handle($this->multi, $handle);
            curl_close($handle);
        }

        if (is_resource($this->multi) || $this->multi instanceof \CurlMultiHandle) {
            curl_multi_close($this->multi);
        }
    }

    public function request(string|array $request, array $options, Task $task): mixed
    {
        $payload = $this->normalizeRequest($request, $options);

        $handle = curl_init($payload['url']);
        curl_setopt_array($handle, $payload['options']);

        curl_multi_add_handle($this->multi, $handle);

        $id = (int) $handle;

        $this->handles[$id] = $handle;
        $this->tasks[$id] = $task;

        return Task::suspend();
    }

    public function tick(): bool
    {
        if ($this->handles === []) {
            return false;
        }

        $running = 0;

        do {
            $status = curl_multi_exec($this->multi, $running);
        } while ($status === CURLM_CALL_MULTI_PERFORM);

        $activity = $status === CURLM_CALL_MULTI_PERFORM || $running > 0;

        while ($info = curl_multi_info_read($this->multi)) {
            $handle = $info['handle'];
            $id = (int) $handle;
            $task = $this->tasks[$id] ?? null;
            $content = curl_multi_getcontent($handle);
            $details = curl_getinfo($handle);
            $error = curl_error($handle);

            curl_multi_remove_handle($this->multi, $handle);
            curl_close($handle);

            unset($this->handles[$id], $this->tasks[$id]);

            if ($task) {
                if ($error !== '') {
                    $this->loop->enqueue($task, null, false, new RuntimeException($error));
                } else {
                    $this->loop->enqueue($task, [
                        'body' => $content,
                        'info' => $details,
                        'status' => $details['http_code'] ?? 0,
                    ]);
                }
            }

            $activity = true;
        }

        if ($running > 0) {
            $select = curl_multi_select($this->multi, 0.2);

            if ($select === -1) {
                usleep(1000);
            }

            $activity = true;
        }

        return $activity;
    }

    public function hasPending(): bool
    {
        return $this->handles !== [];
    }

    public static function sync(string|array $request, array $options): array
    {
        $payload = self::normalizeStatic($request, $options);
        $handle = curl_init($payload['url']);
        curl_setopt_array($handle, $payload['options']);

        $content = curl_exec($handle);
        $details = curl_getinfo($handle);
        $error = curl_error($handle);

        curl_close($handle);

        if ($error !== '') {
            throw new RuntimeException($error);
        }

        return [
            'body' => $content,
            'info' => $details,
            'status' => $details['http_code'] ?? 0,
        ];
    }

    private function normalizeRequest(string|array $request, array $options): array
    {
        $payload = self::normalizeStatic($request, $options);
        $payload['options'][CURLOPT_RETURNTRANSFER] = true;

        return $payload;
    }

    private static function normalizeStatic(string|array $request, array $options): array
    {
        if (is_string($request)) {
            $url = $request;
            $extra = [];
        } else {
            $url = $request['url'] ?? '';
            $extra = $request['options'] ?? [];
        }

        if ($url === '') {
            throw new RuntimeException('Request URL is required.');
        }

        $merged = $extra + $options;
        $merged[CURLOPT_RETURNTRANSFER] = true;

        return [
            'url' => $url,
            'options' => $merged,
        ];
    }
}
