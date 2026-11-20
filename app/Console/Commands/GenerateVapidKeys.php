<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;
use Throwable;

#[Signature('webpush:vapid {--show : Tampilkan kunci tanpa menulis ke .env}')]
#[Description('Generate pasangan kunci VAPID untuk notifikasi Web Push')]
class GenerateVapidKeys extends Command
{
    public function handle(): int
    {
        $keys = $this->createKeys();

        if ($this->option('show')) {
            $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
            $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);

            return self::SUCCESS;
        }

        $envPath = base_path('.env');
        if (! file_exists($envPath)) {
            $this->error('File .env tidak ditemukan.');

            return self::FAILURE;
        }

        $content = (string) file_get_contents($envPath);

        foreach ([
            'VAPID_PUBLIC_KEY' => $keys['publicKey'],
            'VAPID_PRIVATE_KEY' => $keys['privateKey'],
        ] as $key => $value) {
            if (preg_match("/^{$key}=.*$/m", $content)) {
                $content = (string) preg_replace("/^{$key}=.*$/m", "{$key}={$value}", $content);
            } else {
                $content = rtrim($content)."\n{$key}={$value}\n";
            }
        }

        file_put_contents($envPath, $content);
        $this->info('Kunci VAPID berhasil dibuat dan disimpan ke .env.');

        return self::SUCCESS;
    }

    /**
     * @return array{publicKey: string, privateKey: string}
     */
    private function createKeys(): array
    {
        try {
            return VAPID::createVapidKeys();
        } catch (Throwable) {
            // Fallback for Windows PHP builds where OPENSSL_CONF is not preconfigured.
            $cnfPath = $this->findOrCreateOpenSslConfig();
            $key = openssl_pkey_new([
                'curve_name' => 'prime256v1',
                'private_key_type' => OPENSSL_KEYTYPE_EC,
                'config' => $cnfPath,
            ]);

            if ($key === false) {
                throw new \RuntimeException('Gagal membuat kunci EC P-256 OpenSSL.');
            }

            $details = openssl_pkey_get_details($key);
            if (! is_array($details) || ! isset($details['ec']['x'], $details['ec']['y'], $details['ec']['d'])) {
                throw new \RuntimeException('Gagal membaca detail kunci EC P-256.');
            }

            $x = str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT);
            $y = str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);
            $d = str_pad($details['ec']['d'], 32, "\0", STR_PAD_LEFT);

            return [
                'publicKey' => $this->base64UrlEncode("\x04".$x.$y),
                'privateKey' => $this->base64UrlEncode($d),
            ];
        }
    }

    private function findOrCreateOpenSslConfig(): string
    {
        $phpDir = dirname(PHP_BINARY);
        foreach ([
            $phpDir.DIRECTORY_SEPARATOR.'extras'.DIRECTORY_SEPARATOR.'ssl'.DIRECTORY_SEPARATOR.'openssl.cnf',
            $phpDir.DIRECTORY_SEPARATOR.'openssl.cnf',
        ] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        $tempCnf = storage_path('framework/cache/openssl-vapid.cnf');
        if (! is_file($tempCnf)) {
            file_put_contents($tempCnf, "[req]\ndistinguished_name = req_distinguished_name\n[req_distinguished_name]\n");
        }

        return $tempCnf;
    }

    private function base64UrlEncode(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }
}
