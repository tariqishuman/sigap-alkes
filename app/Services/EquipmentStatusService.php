<?php

namespace App\Services;

use App\Enums\EquipmentStatus;
use App\Models\Equipment;
use App\Models\StatusHistory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EquipmentStatusService
{
    /**
     * Set initial status for newly registered equipment based on its EquipmentType (PRD §F1 & §F4).
     */
    public function initializeStatus(Equipment $equipment, ?int $changedBy = null): StatusHistory
    {
        $equipment->loadMissing('equipmentType');

        $initialStatus = $equipment->equipmentType?->requires_calibration
            ? EquipmentStatus::CalibrationDue
            : EquipmentStatus::FitForUse;

        $reason = $equipment->equipmentType?->requires_calibration
            ? 'Alat baru wajib kalibrasi (belum memiliki sertifikat)'
            : 'Alat baru tidak wajib kalibrasi';

        return DB::transaction(function () use ($equipment, $initialStatus, $reason, $changedBy) {
            if ($equipment->status !== $initialStatus) {
                $equipment->forceFill(['status' => $initialStatus])->saveQuietly();
            }

            return StatusHistory::create([
                'equipment_id' => $equipment->id,
                'from_status' => null,
                'to_status' => $initialStatus,
                'changed_at' => now(),
                'reason' => $reason,
                'changed_by' => $changedBy ?? Auth::id(),
            ]);
        });
    }
}
