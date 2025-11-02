<?php

declare(strict_types=1);

namespace Restify\Middleware;

use Restify\Http\Request;
use Restify\Http\Response;
use Restify\Support\Logger;
use Throwable;

final class ExceptionMiddleware implements MiddlewareInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(private readonly array $config = [])
    {
    }

    public function process(Request $request, callable $next): Response
    {
        if (!(bool) ($this->config['enabled'] ?? true)) {
            return $next($request);
        }

        try {
            return $next($request);
        } catch (Throwable $throwable) {
            return $this->handleException($request, $throwable);
        }
    }

    private function handleException(Request $request, Throwable $throwable): Response
    {
        if (($this->config['report'] ?? true) === true) {
            $level = (string) ($this->config['log_level'] ?? 'error');

            Logger::log(
                $level,
                'Unhandled exception encountered.',
                [
                    'exception' => [
                        'type' => $throwable::class,
                        'message' => $throwable->getMessage(),
                        'code' => $throwable->getCode(),
                    ],
                    'request' => [
                        'method' => $request->method,
                        'uri' => $request->uri,
                    ],
                ]
            );
        }

        $debug = (bool) ($this->config['debug'] ?? false);
        $includeTrace = $debug || (bool) ($this->config['trace'] ?? false);

        $meta = [
            'error' => [
                'type' => $throwable::class,
                'code' => (int) $throwable->getCode(),
                'message' => $debug ? $throwable->getMessage() : 'An unexpected error occurred.',
            ],
        ];

        if ($includeTrace) {
            $meta['error']['trace'] = $this->formatTrace($throwable);
        }

        return Response::json(
            data: [],
            status: 500,
            meta: $meta,
            message: 'Server Error'
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function formatTrace(Throwable $throwable): array
    {
        $trace = $throwable->getTrace();

        return array_map(
            static function (array $frame): array {
                return [
                    'file' => $frame['file'] ?? null,
                    'line' => $frame['line'] ?? null,
                    'function' => $frame['function'] ?? null,
                    'class' => $frame['class'] ?? null,
                ];
            },
            array_slice($trace, 0, 20)
        );
    }
}
