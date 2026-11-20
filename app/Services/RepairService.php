<?php

namespace App\Services;

use App\Enums\DamageReportStatus;
use App\Enums\EquipmentStatus;
use App\Models\DamageReport;
use App\Models\Equipment;
use App\Models\Repair;
use App\Notifications\EquipmentOperationalNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RepairService
{
    /** Statuses from which a repair may be started (PRD §F4). */
    private const REPAIRABLE_STATUSES = [
        EquipmentStatus::FitForUse,
        EquipmentStatus::Unfit,
        EquipmentStatus::CalibrationDue,
        EquipmentStatus::Overdue,
    ];

    public function __construct(
        private EquipmentStatusService $statusService,
        private SparePartStockService $stockService,
    ) {}

    /**
     * Start a repair from an open damage report.
     * Moves equipment to UnderRepair via EquipmentStatusService and marks the report InProgress.
     * If finished_at is also provided, completes the repair in the same transaction.
     */
    public function start(array $data, ?int $actorId = null): Repair
    {
        $actorId ??= Auth::id();

        $damageReport = DamageReport::with('equipment')->find($data['damage_report_id'] ?? null);

        $validated = Validator::make($data, [
            'damage_report_id' => ['required', 'exists:damage_reports,id', function ($attribute, $value, $fail) use ($damageReport) {
                if (! $damageReport) {
                    return;
                }

                if ($damageReport->status !== DamageReportStatus::Open) {
                    $fail('Perbaikan hanya bisa dimulai dari laporan kerusakan yang berstatus Terbuka.');

                    return;
                }

                $equipment = $damageReport->equipment;

                if (! $equipment?->is_active) {
                    $fail('Alat tidak aktif dan tidak bisa diperbaiki.');

                    return;
                }

                if ($equipment->hasActiveRepair()) {
                    $fail('Alat ini masih memiliki perbaikan aktif yang belum selesai.');

                    return;
                }

                if (! in_array($equipment->status, self::REPAIRABLE_STATUSES, true)) {
                    $fail("Alat dengan status \"{$equipment->status->getLabel()}\" tidak bisa dimulai perbaikannya.");
                }
            }],
            'technician_id' => ['nullable', 'exists:users,id'],
            'started_at' => ['required', 'date'],
            'finished_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'action' => ['nullable', 'required_with:finished_at', 'string', 'max:4000'],
            'labor_cost' => ['nullable', 'numeric', 'min:0'],
            'spare_parts' => ['nullable', 'array'],
            'spare_parts.*.spare_part_id' => ['required', 'exists:spare_parts,id'],
            'spare_parts.*.quantity' => ['required', 'integer', 'min:1'],
            'spare_parts.*.unit_price' => ['required', 'numeric', 'min:0'],
        ], [], $this->validationAttributes())->validate();

        $repair = DB::transaction(function () use ($validated, $actorId): Repair {
            $report = DamageReport::query()
                ->whereKey($validated['damage_report_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $equipment = Equipment::query()
                ->whereKey($report->equipment_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($report->status !== DamageReportStatus::Open) {
                throw ValidationException::withMessages([
                    'damage_report_id' => 'Laporan kerusakan ini sudah memiliki perbaikan.',
                ]);
            }

            if ($equipment->hasActiveRepair()) {
                throw ValidationException::withMessages([
                    'damage_report_id' => 'Alat ini masih memiliki perbaikan aktif yang belum selesai.',
                ]);
            }

            $this->statusService->startRepair($equipment, $actorId);

            $repair = Repair::create([
                'damage_report_id' => $report->id,
                'technician_id' => $validated['technician_id'] ?? $actorId,
                'action' => $validated['action'] ?? null,
                'started_at' => $validated['started_at'],
                'finished_at' => null,
                'labor_cost' => bcadd((string) ($validated['labor_cost'] ?? '0'), '0', 2),
            ]);

            $this->stockService->syncRepairSpareParts($repair, $validated['spare_parts'] ?? [], $actorId);

            $report->update(['status' => DamageReportStatus::InProgress]);

            if (! empty($validated['finished_at'])) {
                $repair->update(['finished_at' => $validated['finished_at']]);
                $this->statusService->finishRepair($equipment, $actorId);
                $report->update(['status' => DamageReportStatus::Resolved]);
            }

            return $repair->fresh(['damageReport.equipment.room', 'technician', 'repairSpareParts.sparePart']);
        });

        if (! EquipmentStatusService::$suppressNotifications && $repair->damageReport?->equipment) {
            $equipment = $repair->damageReport->equipment;
            $roomHeads = app(NotificationRecipientResolver::class)->roomHeadsFor($equipment);

            if ($roomHeads->isNotEmpty()) {
                $event = $repair->isFinished()
                    ? EquipmentOperationalNotification::EVENT_REPAIR_FINISHED
                    : EquipmentOperationalNotification::EVENT_REPAIR_STARTED;

                Notification::send($roomHeads, new EquipmentOperationalNotification($equipment, $event));
            }
        }

        return $repair;
    }

    /**
     * Update an ongoing repair. Finished repairs are locked.
     * Setting finished_at completes the repair and transitions equipment status via EquipmentStatusService.
     */
    public function update(Repair $repair, array $data, ?int $actorId = null): Repair
    {
        if ($repair->isFinished()) {
            throw ValidationException::withMessages([
                'action' => 'Perbaikan yang sudah selesai terkunci dan tidak bisa diubah.',
            ]);
        }

        $actorId ??= Auth::id();
        $hasSparePartsKey = array_key_exists('spare_parts', $data);

        $merged = [
            'technician_id' => $data['technician_id'] ?? $repair->technician_id,
            'started_at' => $data['started_at'] ?? $repair->started_at?->toDateTimeString(),
            'finished_at' => array_key_exists('finished_at', $data) ? $data['finished_at'] : null,
            'action' => array_key_exists('action', $data) ? $data['action'] : $repair->action,
            'labor_cost' => array_key_exists('labor_cost', $data) ? $data['labor_cost'] : $repair->labor_cost,
            'spare_parts' => $hasSparePartsKey ? $data['spare_parts'] : null,
        ];

        $validated = Validator::make($merged, [
            'technician_id' => ['nullable', 'exists:users,id'],
            'started_at' => ['required', 'date'],
            'finished_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'action' => ['nullable', 'required_with:finished_at', 'string', 'max:4000'],
            'labor_cost' => ['nullable', 'numeric', 'min:0'],
            'spare_parts' => ['nullable', 'array'],
            'spare_parts.*.spare_part_id' => ['required', 'exists:spare_parts,id'],
            'spare_parts.*.quantity' => ['required', 'integer', 'min:1'],
            'spare_parts.*.unit_price' => ['required', 'numeric', 'min:0'],
        ], [], $this->validationAttributes())->validate();

        $updated = DB::transaction(function () use ($repair, $validated, $hasSparePartsKey, $actorId): Repair {
            $lockedRepair = Repair::query()->whereKey($repair->id)->lockForUpdate()->firstOrFail();

            if ($lockedRepair->isFinished()) {
                throw ValidationException::withMessages([
                    'action' => 'Perbaikan yang sudah selesai terkunci dan tidak bisa diubah.',
                ]);
            }

            $lockedRepair->update([
                'technician_id' => $validated['technician_id'],
                'action' => $validated['action'],
                'started_at' => $validated['started_at'],
                'finished_at' => $validated['finished_at'] ?? null,
                'labor_cost' => bcadd((string) ($validated['labor_cost'] ?? '0'), '0', 2),
            ]);

            if ($hasSparePartsKey) {
                $this->stockService->syncRepairSpareParts($lockedRepair, $validated['spare_parts'] ?? [], $actorId);
            }

            if (! empty($validated['finished_at'])) {
                $report = $lockedRepair->damageReport()->firstOrFail();
                $equipment = $report->equipment()->firstOrFail();

                $this->statusService->finishRepair($equipment, $actorId);
                $report->update(['status' => DamageReportStatus::Resolved]);
            }

            return $lockedRepair->fresh(['damageReport.equipment.room', 'technician', 'repairSpareParts.sparePart']);
        });

        if (! EquipmentStatusService::$suppressNotifications && ! empty($validated['finished_at']) && $updated->damageReport?->equipment) {
            $equipment = $updated->damageReport->equipment;
            $roomHeads = app(NotificationRecipientResolver::class)->roomHeadsFor($equipment);
            if ($roomHeads->isNotEmpty()) {
                Notification::send($roomHeads, new EquipmentOperationalNotification(
                    $equipment,
                    EquipmentOperationalNotification::EVENT_REPAIR_FINISHED,
                ));
            }
        }

        return $updated;
    }

    /**
     * Mark an ongoing repair as finished.
     */
    public function finish(Repair $repair, array $data = [], ?int $actorId = null): Repair
    {
        return $this->update($repair, [
            ...$data,
            'finished_at' => $data['finished_at'] ?? now(),
        ], $actorId);
    }

    /**
     * @return array<string, string>
     */
    private function validationAttributes(): array
    {
        return [
            'damage_report_id' => 'laporan kerusakan',
            'technician_id' => 'teknisi',
            'started_at' => 'waktu mulai',
            'finished_at' => 'waktu selesai',
            'action' => 'tindakan perbaikan',
            'labor_cost' => 'biaya jasa',
            'spare_parts.*.spare_part_id' => 'suku cadang',
            'spare_parts.*.quantity' => 'jumlah',
            'spare_parts.*.unit_price' => 'harga satuan',
        ];
    }
}
