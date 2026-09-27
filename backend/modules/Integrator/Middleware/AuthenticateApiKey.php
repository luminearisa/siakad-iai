<?php

namespace Modules\Integrator\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Integrator\Models\ApiKey;
use Modules\Integrator\Models\ApiRequestLog;
use Modules\Integrator\Services\ApiKeyService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates integration requests with an `X-API-Key` header.
 *
 * Design notes:
 * - The token is only accepted via header (`X-API-Key` or `Authorization: Bearer`),
 *   never via query string, so credentials cannot end up in web server access logs.
 * - IP allow-list and rate limit are enforced per client, scopes per key.
 * - Every request — accepted or rejected — is written to `api_request_logs` from
 *   {@see terminate()} so the audit trail survives even when the response is a 401.
 */
class AuthenticateApiKey
{
    public function __construct(
        protected ApiKeyService $keyService
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$scopes): Response
    {
        $request->attributes->set('integrator.started_at', microtime(true));

        $token = $this->extractToken($request);

        if ($token === null) {
            return $this->deny($request, 401, 'API key required.');
        }

        $key = $this->keyService->resolve($token);

        if (! $key) {
            return $this->deny($request, 401, 'Invalid API key.');
        }

        // Bound to the request so controllers, resources and the audit log can see it.
        $request->attributes->set('integrator.api_key', $key);

        if ($key->isRevoked()) {
            return $this->deny($request, 401, 'API key has been revoked.');
        }

        if ($key->isExpired()) {
            return $this->deny($request, 401, 'API key has expired.');
        }

        $client = $key->client;

        if (! $client || ! $client->is_active) {
            return $this->deny($request, 401, 'API client is inactive.');
        }

        if (! $client->allowsIp((string) $request->ip())) {
            return $this->deny($request, 403, 'Request IP is not allowed for this API client.', [
                'client' => $client->slug,
            ]);
        }

        $limit = max(1, (int) $client->rate_limit_per_minute);
        $bucket = 'integrator:'.$key->getKey();

        if (RateLimiter::tooManyAttempts($bucket, $limit)) {
            $retryAfter = RateLimiter::availableIn($bucket);
            $request->attributes->set('integrator.retry_after', $retryAfter);

            return $this->deny($request, 429, 'Rate limit exceeded.', [
                'retry_after' => $retryAfter,
                'limit_per_minute' => $limit,
            ]);
        }

        RateLimiter::hit($bucket, 60);

        if ($scopes !== [] && ! $key->can($scopes)) {
            return $this->deny($request, 403, 'API key is missing a required scope.', [
                'required_scopes' => $this->normaliseScopes($scopes),
                'granted_scopes' => $key->scopeList(),
            ]);
        }

        $response = $next($request);

        $response->headers->set('X-RateLimit-Limit', (string) $limit);
        $response->headers->set('X-RateLimit-Remaining', (string) RateLimiter::remaining($bucket, $limit));
        $response->headers->set('X-Integrator-Client', (string) $client->slug);

        return $response;
    }

    /**
     * Persist the audit row once the response has been sent.
     */
    public function terminate(Request $request, Response $response): void
    {
        $key = $request->attributes->get('integrator.api_key');
        $denial = $request->attributes->get('integrator.denial');

        if (! $key instanceof ApiKey && $denial === null) {
            return;
        }

        $startedAt = (float) $request->attributes->get('integrator.started_at', microtime(true));

        try {
            ApiRequestLog::create([
                'api_client_id' => $key instanceof ApiKey ? $key->api_client_id : null,
                'api_key_id' => $key instanceof ApiKey ? $key->getKey() : null,
                'method' => $request->method(),
                'path' => '/'.ltrim($request->path(), '/'),
                'query' => $this->sanitisedQuery($request),
                'status_code' => $response->getStatusCode(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent() ? mb_substr($request->userAgent(), 0, 500) : null,
                'error_message' => $denial['message'] ?? null,
                'created_at' => now(),
            ]);

            if ($key instanceof ApiKey) {
                $this->keyService->recordUsage($key, $request->ip());
            }
        } catch (\Throwable) {
            // Auditing must never turn a served response into a 500.
        }
    }

    /**
     * Pull the token out of the request headers.
     */
    protected function extractToken(Request $request): ?string
    {
        $header = $request->header('X-API-Key');

        if (is_string($header) && trim($header) !== '') {
            return trim($header);
        }

        $bearer = $request->bearerToken();

        return is_string($bearer) && $bearer !== '' ? $bearer : null;
    }

    /**
     * Build a rejection response and remember why, for the audit log.
     */
    protected function deny(Request $request, int $status, string $message, array $errors = []): Response
    {
        $request->attributes->set('integrator.denial', [
            'status' => $status,
            'message' => $message,
            'errors' => $errors ?: null,
        ]);

        $response = \App\Support\ApiResponse::error(
            message: $message,
            code: $status,
            errors: $errors ?: null
        );

        if ($status === 429) {
            $retryAfter = (int) $request->attributes->get('integrator.retry_after', 60);
            $response->headers->set('Retry-After', (string) $retryAfter);
        }

        return $response;
    }

    /**
     * Never store the credential, even if someone sends it in the query string.
     *
     * @return array<string, mixed>|null
     */
    protected function sanitisedQuery(Request $request): ?array
    {
        $query = $request->query();

        if ($query === []) {
            return null;
        }

        unset($query['api_key'], $query['token'], $query['key']);

        return $query;
    }

    /**
     * Normalise `a|b` middleware arguments into a flat scope list.
     *
     * @param  array<int, string>  $scopes
     * @return array<int, string>
     */
    protected function normaliseScopes(array $scopes): array
    {
        $flat = [];

        foreach ($scopes as $scope) {
            foreach (explode('|', $scope) as $part) {
                $part = trim($part);

                if ($part !== '') {
                    $flat[] = $part;
                }
            }
        }

        return array_values(array_unique($flat));
    }
}
