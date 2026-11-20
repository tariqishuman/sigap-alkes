<?php

namespace App\Services;

use App\Enums\DamageReportStatus;
use App\Enums\UserRole;
use App\Models\DamageReport;
use App\Models\Equipment;
use App\Models\User;
use App\Notifications\DamageReportedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DamageReportService
{
    /**
     * Create a new damage report without changing equipment status (PRD §F3 & §F4).
     * Sends a Filament database notification to teknisi and admin_ipsrs.
     */
    public function report(array $data, User $reporter): DamageReport
    {
        $equipment = Equipment::find($data['equipment_id'] ?? null);

        $validated = Validator::make($data, [
            'equipment_id' => ['required', 'exists:equipment,id', function ($attribute, $value, $fail) use ($equipment, $reporter) {
                if (! $equipment?->is_active) {
                    $fail('Laporan kerusakan hanya bisa dibuat untuk alat yang masih aktif.');

                    return;
                }

                $isRoomHeadOnly = $reporter->hasRole(UserRole::KepalaRuangan->value)
                    && ! $reporter->hasAnyRole([
                        UserRole::SuperAdmin->value,
                        UserRole::AdminIpsrs->value,
                        UserRole::Teknisi->value,
                    ]);

                if ($isRoomHeadOnly && ($reporter->room_id === null || (int) $reporter->room_id !== (int) $equipment->room_id)) {
                    $fail('Kepala ruangan hanya boleh melaporkan kerusakan alat di ruangannya sendiri.');
                }
            }],
            'reported_at' => ['nullable', 'date', 'before_or_equal:now'],
            'complaint' => ['required', 'string', 'max:2000'],
        ], [], [
            'equipment_id' => 'alat',
            'reported_at' => 'waktu lapor',
            'complaint' => 'keluhan',
        ])->validate();

        $report = DB::transaction(function () use ($validated, $reporter): DamageReport {
            return DamageReport::create([
                'equipment_id' => $validated['equipment_id'],
                'reported_by' => $reporter->id,
                'reported_at' => $validated['reported_at'] ?? now(),
                'complaint' => $validated['complaint'],
                'status' => DamageReportStatus::Open,
            ]);
        });

        $recipients = User::role([UserRole::Teknisi->value, UserRole::AdminIpsrs->value])->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new DamageReportedNotification($report));
        }

        return $report;
    }

    /**
     * Update complaint while the report is still open.
     */
    public function update(DamageReport $report, array $data): DamageReport
    {
        if (! $report->isOpen()) {
            throw ValidationException::withMessages([
                'complaint' => 'Laporan kerusakan yang sudah ditindaklanjuti tidak bisa diubah.',
            ]);
        }

        $validated = Validator::make($data, [
            'complaint' => ['required', 'string', 'max:2000'],
        ], [], [
            'complaint' => 'keluhan',
        ])->validate();

        $report->update([
            'complaint' => $validated['complaint'],
        ]);

        return $report->fresh();
    }
}
