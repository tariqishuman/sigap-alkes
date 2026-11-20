<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SettingsService
{
    private const CACHE_KEY = 'sigap.app_settings';

    private const CACHE_TTL_SECONDS = 300;

    /** Keys whose values must always be stored encrypted and never displayed back. */
    public const ENCRYPTED_KEYS = [
        'satusehat.client_secret',
    ];

    /**
     * Default values for all application settings.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            // Tab 1: Umum
            'general.app_name' => 'SIGAP-Alkes',
            'general.hospital_name' => 'RS Pendidikan ITS',
            'general.hospital_address' => 'Jl. Teknik Kimia, Keputih, Sukolilo, Surabaya',
            'general.hospital_phone' => '(031) 5994251',
            'general.logo_path' => null,
            'general.favicon_path' => null,
            'general.timezone' => 'Asia/Jakarta',

            // Tab 2: Pengingat
            'reminder.days' => [30, 7, 0],
            'reminder.send_time' => '07:00',
            'reminder.email_enabled' => true,

            // Tab 3: Nilai aset & target operasional
            'asset.replace_threshold_percent' => (int) config('sigap.replace_threshold_percent', 50),
            'asset.uptime_target_percent' => 95,

            // Tab 4: Label QR & halaman publik
            'qr.public_base_url' => null,
            'qr.label_footer_text' => 'Scan QR untuk cek status alat',

            // Tab 5: Integrasi SatuSehat
            'satusehat.enabled' => false,
            'satusehat.environment' => 'sandbox',
            'satusehat.auth_url' => 'https://api-satusehat-stg.dto.kemkes.go.id/oauth2/v1',
            'satusehat.fhir_base_url' => 'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1',
            'satusehat.organization_id' => null,
            'satusehat.client_id' => null,
            'satusehat.client_secret' => null,
        ];
    }

    /**
     * Read a non-secret setting with automatic fallback when DB/cache is unavailable.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $fallback = $default ?? ($this->defaults()[$key] ?? null);

        try {
            $all = $this->loadAll();

            if (! array_key_exists($key, $all)) {
                return $fallback;
            }

            $entry = $all[$key];

            // Never return raw encrypted secret via standard get().
            if ($entry['is_encrypted']) {
                return $fallback;
            }

            return $entry['value'] ?? $fallback;
        } catch (Throwable) {
            return $fallback;
        }
    }

    /**
     * Read and decrypt an encrypted setting (for server-side API calls only).
     */
    public function getDecrypted(string $key): ?string
    {
        try {
            $all = $this->loadAll();

            if (! array_key_exists($key, $all) || empty($all[$key]['value'])) {
                return null;
            }

            $raw = (string) $all[$key]['value'];

            return $all[$key]['is_encrypted'] ? Crypt::decryptString($raw) : $raw;
        } catch (Throwable) {
            return null;
        }
    }

    public function hasEncryptedValue(string $key): bool
    {
        try {
            $all = $this->loadAll();

            return array_key_exists($key, $all) && filled($all[$key]['value'] ?? null);
        } catch (Throwable) {
            return false;
        }
    }

    public function set(string $key, mixed $value, ?bool $encrypt = null): void
    {
        $isEncrypted = $encrypt ?? in_array($key, self::ENCRYPTED_KEYS, true);

        $storedValue = $value;
        if ($isEncrypted && filled($value)) {
            $storedValue = Crypt::encryptString((string) $value);
        }

        Setting::updateOrCreate(
            ['key' => $key],
            [
                'value' => $storedValue,
                'is_encrypted' => $isEncrypted,
            ],
        );

        $this->clearCache();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            $isEncrypted = in_array($key, self::ENCRYPTED_KEYS, true);

            // Empty secret input preserves the existing encrypted value.
            if ($isEncrypted && blank($value)) {
                continue;
            }

            $this->set($key, $value, $isEncrypted);
        }
    }

    public function seedDefaults(): void
    {
        foreach ($this->defaults() as $key => $value) {
            $isEncrypted = in_array($key, self::ENCRYPTED_KEYS, true);

            Setting::firstOrCreate(
                ['key' => $key],
                [
                    'value' => $value,
                    'is_encrypted' => $isEncrypted,
                ],
            );
        }

        $this->clearCache();
    }

    public function clearCache(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (Throwable) {
            // Ignore cache errors when store is unavailable.
        }
    }

    public function appName(): string
    {
        return (string) ($this->get('general.app_name') ?: 'SIGAP-Alkes');
    }

    public function hospitalName(): string
    {
        return (string) ($this->get('general.hospital_name') ?: 'RS Pendidikan ITS');
    }

    public function logoUrl(): string
    {
        $path = $this->get('general.logo_path');

        if (is_string($path) && $path !== '') {
            return Storage::disk('public')->url($path);
        }

        return asset('images/logo-its.png');
    }

    public function faviconUrl(): string
    {
        $path = $this->get('general.favicon_path');

        if (is_string($path) && $path !== '') {
            return Storage::disk('public')->url($path);
        }

        return asset('images/brand/favicon.svg');
    }

    public function replaceThresholdPercent(): int
    {
        return max(0, (int) $this->get('asset.replace_threshold_percent', (int) config('sigap.replace_threshold_percent', 50)));
    }

    public function uptimeTargetPercent(): int
    {
        $val = (int) $this->get('asset.uptime_target_percent', 95);

        return max(1, min(100, $val));
    }

    public function publicEquipmentUrl(string $qrToken): string
    {
        $baseUrl = $this->get('qr.public_base_url');

        if (is_string($baseUrl) && trim($baseUrl) !== '') {
            return rtrim(trim($baseUrl), '/').'/alat/'.$qrToken;
        }

        return route('public.equipment.show', ['qr_token' => $qrToken]);
    }

    /**
     * Request an OAuth2 access token from SatuSehat to verify credentials.
     * Never exposes or logs the returned access token or client secret.
     *
     * @return array{ok: bool, message: string}
     */
    public function testSatuSehatConnection(): array
    {
        $authUrl = trim((string) $this->get('satusehat.auth_url', ''));
        $clientId = trim((string) $this->get('satusehat.client_id', ''));
        $clientSecret = $this->getDecrypted('satusehat.client_secret');

        if ($authUrl === '' || $clientId === '' || blank($clientSecret)) {
            return [
                'ok' => false,
                'message' => 'Lengkapi Auth URL, Client ID, dan Client Secret terlebih dahulu.',
            ];
        }

        $endpoint = rtrim($authUrl, '/').'/accesstoken?grant_type=client_credentials';

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->post($endpoint, [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                ]);

            if ($response->successful() && filled($response->json('access_token'))) {
                $this->set('satusehat.last_test_status', 'ok', false);
                $this->set('satusehat.last_test_at', now()->toIso8601String(), false);

                return [
                    'ok' => true,
                    'message' => 'Koneksi SatuSehat berhasil. Autentikasi diterima.',
                ];
            }

            $this->set('satusehat.last_test_status', 'failed', false);
            $this->set('satusehat.last_test_at', now()->toIso8601String(), false);

            return [
                'ok' => false,
                'message' => "Koneksi SatuSehat gagal (HTTP {$response->status()}). Periksa kembali URL dan kredensial.",
            ];
        } catch (Throwable) {
            $this->set('satusehat.last_test_status', 'failed', false);
            $this->set('satusehat.last_test_at', now()->toIso8601String(), false);

            return [
                'ok' => false,
                'message' => 'Tidak dapat terhubung ke server SatuSehat. Periksa URL dan koneksi jaringan.',
            ];
        }
    }

    /**
     * @return array<string, array{value: mixed, is_encrypted: bool}>
     */
    private function loadAll(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): array {
            $result = [];

            foreach (Setting::query()->get(['key', 'value', 'is_encrypted']) as $row) {
                $result[$row->key] = [
                    'value' => $row->value,
                    'is_encrypted' => (bool) $row->is_encrypted,
                ];
            }

            return $result;
        });
    }
}
