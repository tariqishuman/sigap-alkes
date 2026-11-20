<?php

namespace App\Services;

use App\Enums\PreventiveMaintenanceResult;
use App\Models\Equipment;
use App\Models\PreventiveMaintenance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;

class PreventiveMaintenanceService
{
    /**
     * Record a preventive maintenance entry.
     * Computes next_due_at from equipment_type.pm_interval_months and derives result from checklist_results.
     * Never alters equipment status (status changes only through damage reports / calibration / repair).
     */
    public function record(array $data, User $actor): PreventiveMaintenance
    {
        $equipment = Equipment::with('equipmentType')->find($data['equipment_id'] ?? null);

        $validated = Validator::make($data, [
            'equipment_id' => ['required', 'exists:equipment,id', function ($attribute, $value, $fail) use ($equipment) {
                if (! $equipment?->is_active) {
                    $fail('Pemeliharaan preventif hanya dapat dicatat untuk alat yang aktif.');

                    return;
                }

                if (! $equipment->equipmentType?->pm_interval_months) {
                    $fail('Jenis alat ini tidak memiliki jadwal pemeliharaan preventif (interval PM kosong).');
                }
            }],
            'performed_at' => ['required', 'date', 'before_or_equal:today'],
            'checklist_results' => ['required', 'array', 'min:1'],
            'checklist_results.*.item' => ['required', 'string', 'max:255'],
            'checklist_results.*.status' => ['required', 'in:good,needs_attention'],
            'checklist_results.*.note' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'equipment_id' => 'alat',
            'performed_at' => 'tanggal pelaksanaan',
            'checklist_results' => 'daftar pemeriksaan',
            'notes' => 'catatan',
        ])->validate();

        $normalizedChecklist = array_values(array_map(fn (array $row): array => [
            'item' => trim((string) $row['item']),
            'status' => $row['status'] === 'needs_attention' ? 'needs_attention' : 'good',
            'note' => filled($row['note'] ?? null) ? trim((string) $row['note']) : null,
        ], $validated['checklist_results']));

        $hasUnhealthy = collect($normalizedChecklist)->contains(fn (array $row): bool => $row['status'] === 'needs_attention');

        $result = $hasUnhealthy
            ? PreventiveMaintenanceResult::NeedsFollowup
            : PreventiveMaintenanceResult::Good;

        $performedAt = Carbon::parse($validated['performed_at'])->startOfDay();
        $intervalMonths = (int) $equipment->equipmentType->pm_interval_months;
        $nextDueAt = $performedAt->copy()->addMonthsNoOverflow($intervalMonths)->toDateString();

        return PreventiveMaintenance::create([
            'equipment_id' => $equipment->id,
            'performed_at' => $performedAt->toDateString(),
            'performed_by' => $actor->id,
            'checklist_results' => $normalizedChecklist,
            'result' => $result,
            'notes' => filled($validated['notes'] ?? null) ? trim((string) $validated['notes']) : null,
            'next_due_at' => $nextDueAt,
        ]);
    }

    /**
     * PM compliance percentage across active equipment requiring PM.
     *
     * @return array{compliant: int, total: int, rate: float|null}
     */
    public function complianceRate(?int $roomId = null): array
    {
        $equipment = Equipment::query()
            ->where('is_active', true)
            ->when($roomId, fn (Builder $q) => $q->where('room_id', $roomId))
            ->whereHas('equipmentType', fn (Builder $q) => $q->whereNotNull('pm_interval_months')->where('pm_interval_months', '>', 0))
            ->with('latestPreventiveMaintenance')
            ->get();

        $total = $equipment->count();

        if ($total === 0) {
            return ['compliant' => 0, 'total' => 0, 'rate' => null];
        }

        $today = today();
        $compliant = $equipment->filter(function (Equipment $eq) use ($today): bool {
            $due = $eq->latestPreventiveMaintenance?->next_due_at;

            return $due !== null && $due->greaterThanOrEqualTo($today);
        })->count();

        return [
            'compliant' => $compliant,
            'total' => $total,
            'rate' => round(($compliant / $total) * 100, 1),
        ];
    }
}
