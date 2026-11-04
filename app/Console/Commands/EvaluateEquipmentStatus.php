<?php

namespace App\Console\Commands;

use App\Models\Equipment;
use App\Services\EquipmentStatusService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('equipment:evaluate-status')]
#[Description('Perbarui status alat berdasarkan tanggal berlaku sertifikat')]
class EvaluateEquipmentStatus extends Command
{
    public function handle(EquipmentStatusService $statusService): int
    {
        Equipment::query()
            ->where('is_active', true)
            ->chunkById(100, function ($equipmentList) use ($statusService) {
                $equipmentList->each(fn (Equipment $equipment) => $statusService->evaluate($equipment));
            });

        $this->info('Status alat sudah dievaluasi.');

        return self::SUCCESS;
    }
}
