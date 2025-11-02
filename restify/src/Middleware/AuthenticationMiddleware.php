<?php

declare(strict_types=1);

namespace Restify\Middleware;

use PDO;
use Restify\Http\Request;
use Restify\Http\Response;
use Restify\Support\DB;
use Restify\Support\Config;

final class AuthenticationMiddleware implements MiddlewareInterface
{
    /**
     * @var array<string, array<int, array<string, string>>>
     */
    private static array $cache = [];
    /**
     * @var array<string, mixed>
     */
    private array $config;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config ?: Config::get('auth', []);
    }

    public function process(Request $request, callable $next): Response
    {
        if (!(bool) ($this->config['enabled'] ?? true)) {
            return $next($request);
        }

        if ($this->isPublicPath($request->uri)) {
            return $next($request);
        }

        $pdo = DB::connection();

        if (!$pdo instanceof PDO) {
            return $next($request);
        }

        $path = $request->uri;
        $tokens = $this->tokensForEndpoint($pdo, $path);

        if ($tokens === []) {
            return $next($request);
        }

        $authorization = $this->headerValue($request, (string) ($this->config['header'] ?? 'Authorization'));

        if ($authorization === '') {
            return $this->unauthorizedResponse();
        }

        foreach ($tokens as $token) {
            if ($this->matchesToken($authorization, $token)) {
                return $next($request);
            }
        }

        return $this->unauthorizedResponse();
    }

    private function tokensForEndpoint(PDO $pdo, string $endpoint): array
    {
        if (isset(self::$cache[$endpoint])) {
            return self::$cache[$endpoint];
        }

        try {
            $stmt = $pdo->prepare('SELECT token, scheme, algorithm, secret FROM restify_tokens WHERE endpoint = :endpoint');
            $stmt->execute(['endpoint' => $endpoint]);

            /** @var array<int, array<string, string>> $rows */
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            $rows = [];
        }

        return self::$cache[$endpoint] = $rows;
    }

    /**
     * @param array<string, string> $token
     */
    private function matchesToken(string $authorization, array $token): bool
    {
        $scheme = strtolower($token['scheme'] ?? '');
        $algorithm = strtolower($token['algorithm'] ?? '');

        if ($scheme !== '' && !$this->schemeAllowed($scheme)) {
            return false;
        }

        $expectedPrefix = match ($scheme) {
            'basic' => 'basic ',
            'bearer' => 'bearer ',
            default => '',
        };

        $header = strtolower(substr($authorization, 0, strlen($expectedPrefix)));

        if ($expectedPrefix !== '' && $header !== $expectedPrefix) {
            return false;
        }

        $provided = $expectedPrefix === '' ? $authorization : trim(substr($authorization, strlen($expectedPrefix)));

        if ($algorithm === 'jwt') {
            return $this->validateJwt($provided, $token['secret'] ?? '');
        }

        return hash_equals($token['token'] ?? '', $provided);
    }

    private function validateJwt(string $jwt, string $secret): bool
    {
        if ($secret === '' || $jwt === '') {
            return false;
        }

        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            return false;
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        $binarySignature = $this->base64UrlDecode($signatureB64);

        if ($binarySignature === false) {
            return false;
        }

        $expected = hash_hmac('sha256', $headerB64 . '.' . $payloadB64, $secret, true);

        return hash_equals($expected, $binarySignature);
    }

    private function base64UrlDecode(string $data): string|false
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/'), true);
    }

    private function unauthorizedResponse(): Response
    {
        return Response::json(
            data: [],
            status: 401,
            message: 'Unauthorized.'
        );
    }

    private function isPublicPath(string $path): bool
    {
        /** @var array<int, string> $public */
        $public = $this->config['public_paths'] ?? [];

        if ($public === []) {
            return false;
        }

        foreach ($public as $pattern) {
            if ($pattern === '/*') {
                return true;
            }

            if ($pattern === $path) {
                return true;
            }

            if (str_ends_with($pattern, '*')) {
                $prefix = rtrim($pattern, '*');
                if ($prefix === '' || str_starts_with($path, rtrim($prefix, '/'))) {
                    return true;
                }
            }
        }

        return false;
    }

    private function headerValue(Request $request, string $header): string
    {
        foreach ($request->headers as $name => $value) {
            if (strcasecmp($name, $header) === 0) {
                return $value;
            }
        }

        return '';
    }

    private function schemeAllowed(string $scheme): bool
    {
        $allowed = $this->config['schemes'] ?? null;

        if (!is_array($allowed) || $allowed === []) {
            return true;
        }

        return in_array(strtolower($scheme), array_map('strtolower', $allowed), true);
    }
}
