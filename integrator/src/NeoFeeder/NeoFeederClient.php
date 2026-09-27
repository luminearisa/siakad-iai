<?php

declare(strict_types=1);

namespace Integrator\NeoFeeder;

use Integrator\Support\Http;
use RuntimeException;

/**
 * Client for the official Neo Feeder web service.
 *
 * Neo Feeder exposes a single POST endpoint for every function:
 *
 *   POST http://<host>:8100/ws/live2.php      (production)
 *   POST http://<host>:8100/ws/sandbox2.php   (sandbox / uji coba)
 *   body: {"act": "GetToken", "username": "...", "password": "..."}
 *   body: {"act": "InsertBiodataMahasiswa", "token": "...", ...fields}
 *
 * Responses always look like `{"error_code": 0, "error_desc": "", "data": ...}`;
 * `error_code != 0` carries PDDikti's validation message and must be surfaced to the
 * operator instead of being swallowed.
 */
final class NeoFeederClient
{
    private ?string $token = null;

    private int $tokenExpiresAt = 0;

    public function __construct(
        private readonly Http $http,
        private readonly string $baseUrl,
        private readonly string $username,
        private readonly string $password,
        private readonly bool $sandbox = true,
        private readonly string $cachePath = '',
        private readonly bool $verifySsl = false
    ) {
    }

    /**
     * Whether the client has enough configuration to talk to a feeder.
     */
    public function configured(): bool
    {
        return trim($this->baseUrl) !== '' && trim($this->username) !== '' && trim($this->password) !== '';
    }

    /**
     * Full web service endpoint, honouring the sandbox switch.
     */
    public function endpoint(): string
    {
        $base = rtrim($this->baseUrl, '/');

        if (str_ends_with($base, '.php')) {
            return $base;
        }

        return $base.'/ws/'.($this->sandbox ? 'sandbox2.php' : 'live2.php');
    }

    public function isSandbox(): bool
    {
        return $this->sandbox;
    }

    /**
     * Current session token, cached in memory and on disk (25 minutes).
     */
    public function token(bool $refresh = false): string
    {
        if (! $refresh && $this->token !== null && time() < $this->tokenExpiresAt) {
            return $this->token;
        }

        if (! $refresh && $this->cachePath !== '' && is_file($this->cachePath)) {
            $cached = json_decode((string) file_get_contents($this->cachePath), true);

            if (is_array($cached) && ! empty($cached['token']) && (int) ($cached['expires_at'] ?? 0) > time()) {
                $this->token = (string) $cached['token'];
                $this->tokenExpiresAt = (int) $cached['expires_at'];

                return $this->token;
            }
        }

        $response = $this->raw('GetToken', [
            'username' => $this->username,
            'password' => $this->password,
        ]);

        if ($response->transportError() !== null) {
            throw new RuntimeException('Koneksi ke Neo Feeder gagal: '.$response->transportError());
        }

        if (! $response->isOk()) {
            throw new RuntimeException('Login Neo Feeder ditolak: '.$response->message());
        }

        $data = $response->data();
        $token = is_array($data)
            ? (string) ($data['token'] ?? '')
            : (string) $data;

        if ($token === '') {
            $token = (string) (($response->payload()['token'] ?? ''));
        }

        if ($token === '') {
            throw new RuntimeException('Neo Feeder tidak mengembalikan token. Periksa username/password dan URL WS.');
        }

        $this->token = $token;
        $this->tokenExpiresAt = time() + 1500;

        if ($this->cachePath !== '') {
            $directory = dirname($this->cachePath);

            if (! is_dir($directory)) {
                mkdir($directory, 0775, true);
            }

            file_put_contents($this->cachePath, json_encode([
                'token' => $token,
                'expires_at' => $this->tokenExpiresAt,
                'sandbox' => $this->sandbox,
            ]));
        }

        return $token;
    }

    public function forgetToken(): void
    {
        $this->token = null;
        $this->tokenExpiresAt = 0;

        if ($this->cachePath !== '' && is_file($this->cachePath)) {
            @unlink($this->cachePath);
        }
    }

    /**
     * Call one Neo Feeder function.
     *
     * A stale token is retried once with a fresh login, which is what keeps long
     * imports from dying halfway through.
     *
     * @param  array<string, mixed>  $payload
     */
    public function call(string $act, array $payload = [], bool $retryOnExpiredToken = true): FeederResponse
    {
        $response = $this->raw($act, ['token' => $this->token()] + $payload);

        if ($response->transportError() !== null) {
            return $response;
        }

        $errorCode = $response->errorCode();
        $errorDesc = $response->errorDesc();

        $isExpired = $retryOnExpiredToken
            && $errorCode !== 0
            && $this->looksLikeExpiredToken($errorDesc);

        if ($isExpired) {
            $this->forgetToken();

            return $this->call($act, $payload, false);
        }

        return $response;
    }

    /**
     * Insert/update helper that throws on validation errors.
     *
     * @param  array<string, mixed>  $payload
     */
    public function push(string $act, array $payload): FeederResponse
    {
        $response = $this->call($act, $payload);

        if (! $response->isOk()) {
            throw new RuntimeException($response->message(), $response->errorCode());
        }

        return $response;
    }

    /**
     * Verify connectivity and credentials: token + institution profile.
     *
     * @return array<string, mixed>
     */
    public function ping(): array
    {
        $response = $this->call('GetProfilPT', ['limit' => 1]);

        $profile = $response->rows()[0] ?? null;

        return [
            'endpoint' => $this->endpoint(),
            'sandbox' => $this->sandbox,
            'error_code' => $response->errorCode(),
            'error_desc' => $response->errorDesc(),
            'profile' => $profile,
        ];
    }

    /**
     * Request the field dictionary (`GetDictionary`), used to confirm field names
     * against the installed Neo Feeder version.
     *
     * @return array<int, array<string, mixed>>
     */
    public function dictionary(): array
    {
        return $this->call('GetDictionary', ['limit' => 0, 'offset' => 0])->rows();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function raw(string $act, array $payload): FeederResponse
    {
        $response = $this->http
            ->withVerifySsl($this->verifySsl)
            ->postJson($this->endpoint(), ['act' => $act] + $payload, ['Accept' => 'application/json']);

        return FeederResponse::fromHttp($act, $response);
    }

    private function looksLikeExpiredToken(string $description): bool
    {
        $needle = mb_strtolower($description);

        foreach (['token', 'session', 'login', 'akses'] as $keyword) {
            if (str_contains($needle, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
