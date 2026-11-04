<?php

namespace App\Services;

use App\Enums\EquipmentStatus;
use App\Models\Equipment;
use App\Models\StatusHistory;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EquipmentStatusService
{
    /**
     * Daily date-based rules: Laik pakai -> Perlu kalibrasi from H-30,
     * Perlu kalibrasi -> Terlambat once valid_until has passed.
     * Both checks run in order, so equipment missed by the scheduler still catches up.
     */
    public function evaluate(Equipment $equipment): void
    {
        $validUntil = $equipment->latestValidCalibration()->first()?->valid_until;

        if (! $validUntil) {
            return;
        }

        if ($equipment->status === EquipmentStatus::FitForUse && today()->gte($validUntil->copy()->subDays(30))) {
            $this->transition($equipment, [EquipmentStatus::FitForUse], EquipmentStatus::CalibrationDue, 'Sertifikat berakhir dalam 30 hari');
        }

        if ($equipment->status === EquipmentStatus::CalibrationDue && today()->gt($validUntil)) {
            $this->transition($equipment, [EquipmentStatus::CalibrationDue], EquipmentStatus::Overdue, 'Masa berlaku sertifikat sudah lewat');
        }
    }

    /**
     * Moving equipment that needs installation, or reinstalling any equipment,
     * voids its fit-for-use status. Equipment in any other status is left as it is.
     */
    public function handleMovement(Equipment $equipment, bool $isReinstallation, ?int $changedBy = null): void
    {
        $equipment->loadMissing('equipmentType');

        if ($equipment->status !== EquipmentStatus::FitForUse) {
            return;
        }

        if ($isReinstallation) {
            $this->transition($equipment, [EquipmentStatus::FitForUse], EquipmentStatus::CalibrationDue, 'Reinstalasi alat', $changedBy);
        } elseif ($equipment->equipmentType->requires_installation) {
            $this->transition($equipment, [EquipmentStatus::FitForUse], EquipmentStatus::CalibrationDue, 'Mutasi alat yang memerlukan instalasi', $changedBy);
        }
    }

    public function startRepair(Equipment $equipment, ?int $changedBy = null): StatusHistory
    {
        return $this->transition($equipment, [EquipmentStatus::FitForUse, EquipmentStatus::Unfit], EquipmentStatus::UnderRepair, 'Perbaikan dimulai', $changedBy);
    }

    public function finishRepair(Equipment $equipment, ?int $changedBy = null): StatusHistory
    {
        $equipment->loadMissing('equipmentType');

        return $equipment->equipmentType->requires_calibration
            ? $this->transition($equipment, [EquipmentStatus::UnderRepair], EquipmentStatus::CalibrationDue, 'Perbaikan selesai, wajib kalibrasi ulang', $changedBy)
            : $this->transition($equipment, [EquipmentStatus::UnderRepair], EquipmentStatus::FitForUse, 'Perbaikan selesai', $changedBy);
    }

    public function startTesting(Equipment $equipment, ?int $changedBy = null): StatusHistory
    {
        return $this->transition($equipment, [EquipmentStatus::CalibrationDue, EquipmentStatus::Overdue], EquipmentStatus::UnderTesting, 'Alat mulai diuji', $changedBy);
    }

    public function recordTestResult(Equipment $equipment, bool $passed, ?int $changedBy = null): StatusHistory
    {
        return $passed
            ? $this->transition($equipment, [EquipmentStatus::UnderTesting], EquipmentStatus::FitForUse, 'Hasil uji lulus', $changedBy)
            : $this->transition($equipment, [EquipmentStatus::UnderTesting], EquipmentStatus::Unfit, 'Hasil uji tidak lulus', $changedBy);
    }

    /**
     * The single place that writes a status change. Each public method above is one
     * row of the PRD §F4 table and declares the statuses it may start from. The current
     * status is re-read under a lock, so two simultaneous requests cannot both apply it.
     *
     * @param  array<int, EquipmentStatus>  $allowedFrom
     *
     * @throws DomainException when the trigger is not allowed from the current status
     */
    private function transition(Equipment $equipment, array $allowedFrom, EquipmentStatus $to, string $reason, ?int $changedBy = null): StatusHistory
    {
        return DB::transaction(function () use ($equipment, $allowedFrom, $to, $reason, $changedBy) {
            $from = Equipment::query()->whereKey($equipment->id)->lockForUpdate()->firstOrFail()->status;

            if (! in_array($from, $allowedFrom, true)) {
                throw new DomainException("Status alat tidak bisa berubah dari \"{$from->getLabel()}\" ke \"{$to->getLabel()}\".");
            }

            $equipment->forceFill(['status' => $to])->save();

            return StatusHistory::create([
                'equipment_id' => $equipment->id,
                'from_status' => $from,
                'to_status' => $to,
                'changed_at' => now(),
                'reason' => $reason,
                'changed_by' => $changedBy ?? Auth::id(),
            ]);
        });
    }

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
