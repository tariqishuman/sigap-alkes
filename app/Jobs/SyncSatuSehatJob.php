<?php

namespace App\Jobs;

use App\Services\SatuSehatSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncSatuSehatJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public ?string $resource = null,
    ) {}

    public function handle(SatuSehatSyncService $syncService): void
    {
        if ($this->resource === null || $this->resource === 'organization') {
            $syncService->syncIpsrsOrganization();
        }

        if ($this->resource === null || $this->resource === 'location') {
            $syncService->syncAllLocations();
        }
    }
}
