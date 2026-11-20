<?php

namespace App\Console\Commands;

use App\Jobs\SyncSatuSehatJob;
use App\Services\SatuSehatClient;
use Illuminate\Console\Command;

class SyncSatuSehatCommand extends Command
{
    protected $signature = 'satusehat:sync {--resource= : organization atau location} {--now : Jalankan langsung tanpa menunggu worker antrean}';

    protected $description = 'Sinkronisasi sub-organisasi IPSRS dan Location ruangan ke SatuSehat (sandbox/production)';

    public function handle(SatuSehatClient $client): int
    {
        if (! $client->isEnabled()) {
            $this->warn('Integrasi SatuSehat tidak aktif di Pengaturan Aplikasi. Tidak ada permintaan yang dikirim.');

            return self::SUCCESS;
        }

        $resource = $this->option('resource');
        if ($resource !== null && ! in_array($resource, ['organization', 'location'], true)) {
            $this->error('Opsi --resource hanya menerima: organization atau location.');

            return self::INVALID;
        }

        if ($this->option('now')) {
            SyncSatuSehatJob::dispatchSync($resource);
            $this->info('Sinkronisasi SatuSehat selesai dijalankan langsung.');
        } else {
            SyncSatuSehatJob::dispatch($resource);
            $this->info('Job sinkronisasi SatuSehat berhasil dimasukkan ke antrean.');
        }

        return self::SUCCESS;
    }
}
