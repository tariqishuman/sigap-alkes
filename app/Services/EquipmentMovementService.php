<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\EquipmentMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EquipmentMovementService
{
    public function __construct(private EquipmentStatusService $statusService) {}

    /**
     * Record an equipment room transfer in one transaction (PRD §F3 & §F4):
     * - Automatically fills from_room_id from the equipment's current room.
     * - Rejects moving to the same room.
     * - Updates equipment.room_id.
     * - Delegates status transition to EquipmentStatusService::handleMovement().
     */
    public function move(array $data, ?int $movedBy = null): EquipmentMovement
    {
        $movedBy ??= Auth::id();
        $equipment = Equipment::find($data['equipment_id'] ?? null);

        $validated = Validator::make($data, [
            'equipment_id' => ['required', 'exists:equipment,id', function ($attribute, $value, $fail) use ($equipment) {
                if (! $equipment?->is_active) {
                    $fail('Mutasi hanya bisa dicatat untuk alat yang masih aktif.');
                }
            }],
            'to_room_id' => ['required', 'exists:rooms,id', function ($attribute, $value, $fail) use ($equipment) {
                if ($equipment && (int) $value === (int) $equipment->room_id) {
                    $fail('Ruangan tujuan harus berbeda dari ruangan alat saat ini.');
                }
            }],
            'moved_at' => ['nullable', 'date', 'before_or_equal:now'],
            'is_reinstallation' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'equipment_id' => 'alat',
            'to_room_id' => 'ruangan tujuan',
            'moved_at' => 'tanggal mutasi',
            'is_reinstallation' => 'reinstalasi',
            'notes' => 'catatan',
        ])->validate();

        return DB::transaction(function () use ($validated, $movedBy): EquipmentMovement {
            $lockedEquipment = Equipment::query()
                ->whereKey($validated['equipment_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $validated['to_room_id'] === (int) $lockedEquipment->room_id) {
                throw ValidationException::withMessages([
                    'to_room_id' => 'Ruangan tujuan harus berbeda dari ruangan alat saat ini.',
                ]);
            }

            $fromRoomId = $lockedEquipment->room_id;
            $isReinstallation = (bool) ($validated['is_reinstallation'] ?? false);

            $movement = EquipmentMovement::create([
                'equipment_id' => $lockedEquipment->id,
                'from_room_id' => $fromRoomId,
                'to_room_id' => $validated['to_room_id'],
                'moved_by' => $movedBy,
                'moved_at' => $validated['moved_at'] ?? now(),
                'is_reinstallation' => $isReinstallation,
                'notes' => $validated['notes'] ?? null,
            ]);

            $lockedEquipment->update(['room_id' => $validated['to_room_id']]);

            $this->statusService->handleMovement($lockedEquipment, $isReinstallation, $movedBy);

            return $movement->fresh(['equipment', 'fromRoom', 'toRoom', 'movedByUser']);
        });
    }
}
