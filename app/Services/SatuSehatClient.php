<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SatuSehatClient
{
    private const TOKEN_CACHE_KEY = 'sigap.satusehat.access_token';

    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) $this->settings->get('satusehat.enabled', false);
    }

    public function organizationId(): string
    {
        return trim((string) $this->settings->get('satusehat.organization_id', ''));
    }

    public function fhirBaseUrl(): string
    {
        return rtrim(trim((string) $this->settings->get('satusehat.fhir_base_url', '')), '/');
    }

    public function authBaseUrl(): string
    {
        return rtrim(trim((string) $this->settings->get('satusehat.auth_url', '')), '/');
    }

    /**
     * Fetch OAuth2 client_credentials access token and cache it until shortly before expiry (~50 min).
     * Never logs or exposes client_secret or access_token.
     */
    public function getAccessToken(bool $forceRefresh = false): string
    {
        $cacheKey = self::TOKEN_CACHE_KEY.'.'.md5($this->authBaseUrl().'|'.$this->settings->get('satusehat.client_id', ''));

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $authUrl = $this->authBaseUrl();
        $clientId = trim((string) $this->settings->get('satusehat.client_id', ''));
        $clientSecret = $this->settings->getDecrypted('satusehat.client_secret');

        if ($authUrl === '' || $clientId === '' || blank($clientSecret)) {
            throw new RuntimeException('Kredensial SatuSehat belum lengkap di Pengaturan Aplikasi.');
        }

        $endpoint = $authUrl.'/accesstoken?grant_type=client_credentials';

        $response = Http::asForm()
            ->timeout(15)
            ->retry(3, fn (int $attempt): int => $attempt * 400, fn ($exception): bool => $this->shouldRetry($exception), throw: false)
            ->post($endpoint, [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

        $token = $response->json('access_token');
        if (! $response->successful() || ! is_string($token) || $token === '') {
            throw new RuntimeException("Gagal memperoleh token SatuSehat (HTTP {$response->status()}).");
        }

        $expiresIn = (int) $response->json('expires_in', 3600);
        // Cache until ~50 minutes (or expires_in - 300s), clamped to 60..3000 seconds.
        $ttl = max(60, min(3000, $expiresIn - 300));
        Cache::put($cacheKey, $token, $ttl);

        return $token;
    }

    /**
     * Send POST request to a SatuSehat FHIR R4 resource endpoint with retry/backoff for 429/5xx.
     *
     * @param  array<string, mixed>  $payload
     */
    public function post(string $resource, array $payload): Response
    {
        $token = $this->getAccessToken();
        $url = $this->fhirBaseUrl().'/'.ltrim($resource, '/');

        return Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout(15)
            ->retry(3, fn (int $attempt): int => $attempt * 500, fn ($exception): bool => $this->shouldRetry($exception), throw: false)
            ->post($url, $payload);
    }

    /**
     * Send PUT request to a SatuSehat FHIR R4 resource endpoint with retry/backoff for 429/5xx.
     *
     * @param  array<string, mixed>  $payload
     */
    public function put(string $resource, string $id, array $payload): Response
    {
        $token = $this->getAccessToken();
        $url = $this->fhirBaseUrl().'/'.ltrim($resource, '/').'/'.$id;

        return Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout(15)
            ->retry(3, fn (int $attempt): int => $attempt * 500, fn ($exception): bool => $this->shouldRetry($exception), throw: false)
            ->put($url, $payload);
    }

    private function shouldRetry(mixed $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        if ($exception instanceof RequestException) {
            return in_array($exception->response->status(), [429, 500, 502, 503, 504], true);
        }

        return false;
    }
}
