<?php

declare(strict_types=1);

namespace Restify\CLI;

use PDO;
use Restify\Support\DB;
use Restify\Support\Schema;
use RuntimeException;

final class AuthenticationCommand implements CommandContract
{
    public function __construct(private readonly string $rootPath)
    {
    }

    public function signature(): string
    {
        return 'authentication';
    }

    public function description(): string
    {
        return 'Generate authentication tokens for protected endpoints.';
    }

    public function usage(): string
    {
        return 'php restify-cli authentication';
    }

    public function handle(array $arguments): int
    {
        $pdo = DB::connection();

        if (!$pdo instanceof PDO) {
            throw new RuntimeException('Authentication management requires a PDO-enabled database connection.');
        }

        Schema::ensureTokensTable($pdo);

        $algorithm = $this->promptEnum('Choose hashing algorithm (md5, sha1, jwt): ', ['md5', 'sha1', 'jwt']);
        $endpoint = $this->prompt('API endpoint (e.g. /api/posts): ');

        if ($endpoint === '') {
            throw new RuntimeException('Endpoint is required.');
        }

        $scheme = $this->promptEnum('Authentication scheme (basic, bearer): ', ['basic', 'bearer']);

        [$token, $secret] = $this->generateToken($algorithm, $endpoint);

        $stmt = $pdo->prepare(
            'INSERT INTO restify_tokens (endpoint, token, scheme, algorithm, secret) VALUES (:endpoint, :token, :scheme, :algorithm, :secret)'
        );

        $stmt->execute([
            'endpoint' => $endpoint,
            'token' => $token,
            'scheme' => $scheme,
            'algorithm' => $algorithm,
            'secret' => $secret,
        ]);

        echo PHP_EOL . 'Token generated successfully!' . PHP_EOL;
        echo "Endpoint : {$endpoint}" . PHP_EOL;
        echo "Scheme   : " . strtoupper($scheme) . PHP_EOL;
        echo "Algorithm: " . strtoupper($algorithm) . PHP_EOL;
        echo "Token    : {$token}" . PHP_EOL;

        if ($secret !== null) {
            echo "Secret   : {$secret}" . PHP_EOL;
        }

        return 0;
    }

    private function prompt(string $question): string
    {
        fwrite(STDOUT, $question);

        $input = trim((string) fgets(STDIN));

        return $input;
    }

    /**
     * @param array<int, string> $options
     */
    private function promptEnum(string $question, array $options): string
    {
        $set = array_map('strtolower', $options);

        while (true) {
            $answer = strtolower($this->prompt($question));

            if ($answer !== '' && in_array($answer, $set, true)) {
                return $answer;
            }

            fwrite(STDOUT, 'Invalid option. Allowed: ' . implode(', ', $options) . PHP_EOL);
        }
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function generateToken(string $algorithm, string $endpoint): array
    {
        return match ($algorithm) {
            'md5' => [md5(bin2hex(random_bytes(16))), null],
            'sha1' => [sha1(bin2hex(random_bytes(20))), null],
            'jwt' => $this->generateJwt($endpoint),
            default => throw new RuntimeException('Unsupported algorithm.'),
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function generateJwt(string $endpoint): array
    {
        $secret = getenv('AUTH_SECRET') ?: bin2hex(random_bytes(32));

        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'HS256',
            'typ' => 'JWT',
        ], JSON_THROW_ON_ERROR));

        $payload = $this->base64UrlEncode(json_encode([
            'iss' => getenv('APP_URL') ?: 'restify',
            'iat' => time(),
            'endpoint' => $endpoint,
        ], JSON_THROW_ON_ERROR));

        $signature = $this->base64UrlEncode(
            hash_hmac('sha256', $header . '.' . $payload, $secret, true)
        );

        return [$header . '.' . $payload . '.' . $signature, $secret];
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
