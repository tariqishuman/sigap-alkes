<?php

namespace App\Services;

use App\Enums\DamageReportStatus;
use App\Enums\EquipmentStatus;
use App\Enums\UserRole;
use App\Models\DamageReport;
use App\Models\DamageReportNote;
use App\Models\Equipment;
use App\Models\User;
use App\Notifications\DamageReportedNotification;
use App\Notifications\UnsafeReportRateLimitNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DamageReportService
{
    public const MAX_UNSAFE_REPORTS_PER_HOUR = 5;

    public function __construct(
        private EquipmentStatusService $statusService,
    ) {}

    /**
     * Create a damage report (or append a note to an active report on the same equipment).
     *
     * Rules (Milestone R3):
     * - Complaint minimum 10 chars, optional up to 3 private photos.
     * - Unsafe report from teknisi/admin_ipsrs/kepala_ruangan transitions equipment to Unfit via EquipmentStatusService.
     * - Unsafe report from magang starts as PendingVerification without changing equipment status.
     * - Duplicate active report on the same equipment appends to damage_report_notes (no new notification unless escalating safe -> unsafe).
     * - Max 5 unsafe reports per user per hour; exceeding triggers Penting notification to admin_ipsrs.
     */
    public function report(array $data, User $reporter): DamageReport
    {
        $equipment = Equipment::find($data['equipment_id'] ?? null);

        if (! array_key_exists('is_safe_to_use', $data)) {
            $data['is_safe_to_use'] = true;
        }

        $validated = Validator::make($data, [
            'equipment_id' => ['required', 'exists:equipment,id', function ($attribute, $value, $fail) use ($equipment, $reporter) {
                if (! $equipment?->is_active) {
                    $fail('Laporan kerusakan hanya bisa dibuat untuk alat yang masih aktif.');

                    return;
                }

                if (! $this->canReportEquipment($reporter, $equipment)) {
                    $fail('Kepala ruangan hanya boleh melaporkan kerusakan alat di ruangannya sendiri.');
                }
            }],
            'reported_at' => ['nullable', 'date', 'before_or_equal:now'],
            'complaint' => ['required', 'string', 'min:10', 'max:2000'],
            'is_safe_to_use' => ['required', 'boolean'],
            'photos' => ['nullable', 'array', 'max:3'],
            'photos.*' => ['string', 'max:500'],
        ], [], [
            'equipment_id' => 'alat',
            'reported_at' => 'waktu lapor',
            'complaint' => 'keluhan',
            'is_safe_to_use' => 'keamanan pemakaian alat',
            'photos' => 'foto kerusakan',
        ])->validate();

        $isSafeToUse = (bool) $validated['is_safe_to_use'];
        $photos = array_values(array_filter($validated['photos'] ?? [], fn ($p) => is_string($p) && $p !== ''));
        $reportedAt = $validated['reported_at'] ?? now();
        $isMagangOnly = $this->isMagangOnly($reporter);

        if (! $isSafeToUse) {
            $recentUnsafeCount = $this->countUnsafeReportsInLastHour($reporter);

            if ($recentUnsafeCount >= self::MAX_UNSAFE_REPORTS_PER_HOUR) {
                $admins = User::role([UserRole::AdminIpsrs->value])->get();

                if ($admins->isNotEmpty()) {
                    Notification::send($admins, new UnsafeReportRateLimitNotification($reporter, self::MAX_UNSAFE_REPORTS_PER_HOUR));
                }

                throw ValidationException::withMessages([
                    'is_safe_to_use' => 'Batas maksimal '.self::MAX_UNSAFE_REPORTS_PER_HOUR.' laporan "tidak aman dipakai" per jam telah tercapai. Hubungi IPSRS secara langsung bila ada kondisi darurat.',
                ]);
            }
        }

        $shouldNotify = false;
        $wasMerged = false;

        $report = DB::transaction(function () use (
            $validated,
            $reporter,
            $isSafeToUse,
            $photos,
            $reportedAt,
            $isMagangOnly,
            &$shouldNotify,
            &$wasMerged,
        ): DamageReport {
            $equipment = Equipment::query()->whereKey($validated['equipment_id'])->lockForUpdate()->firstOrFail();

            $activeReport = DamageReport::query()
                ->where('equipment_id', $equipment->id)
                ->whereIn('status', [
                    DamageReportStatus::PendingVerification->value,
                    DamageReportStatus::Open->value,
                    DamageReportStatus::InProgress->value,
                ])
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($activeReport) {
                $wasMerged = true;

                DamageReportNote::create([
                    'damage_report_id' => $activeReport->id,
                    'reported_by' => $reporter->id,
                    'content' => $validated['complaint'],
                    'is_safe_to_use' => $isSafeToUse,
                    'photos' => $photos !== [] ? $photos : null,
                    'reported_at' => $reportedAt,
                ]);

                // Only escalate & notify when the original report was "safe" and this note states "unsafe".
                if ($activeReport->is_safe_to_use && ! $isSafeToUse) {
                    $shouldNotify = true;

                    if ($isMagangOnly) {
                        $updates = ['is_safe_to_use' => false];
                        if ($activeReport->status === DamageReportStatus::Open) {
                            $updates['status'] = DamageReportStatus::PendingVerification;
                        }
                        $activeReport->update($updates);
                    } else {
                        $history = $this->statusService->reportUnsafe(
                            $equipment,
                            $reporter->id,
                            'Keterangan tambahan: alat tidak aman dipakai',
                        );

                        $activeReport->update([
                            'is_safe_to_use' => false,
                            'marked_unfit_by_report' => $activeReport->marked_unfit_by_report || $history !== null,
                        ]);
                    }
                }

                return $activeReport->fresh();
            }

            $initialStatus = (! $isSafeToUse && $isMagangOnly)
                ? DamageReportStatus::PendingVerification
                : DamageReportStatus::Open;

            $report = DamageReport::create([
                'equipment_id' => $equipment->id,
                'reported_by' => $reporter->id,
                'reported_at' => $reportedAt,
                'complaint' => $validated['complaint'],
                'is_safe_to_use' => $isSafeToUse,
                'photos' => $photos !== [] ? $photos : null,
                'status' => $initialStatus,
                'marked_unfit_by_report' => false,
            ]);

            if (! $isSafeToUse && ! $isMagangOnly) {
                $history = $this->statusService->reportUnsafe(
                    $equipment,
                    $reporter->id,
                    'Laporan kerusakan: alat tidak aman dipakai',
                );

                if ($history !== null) {
                    $report->update(['marked_unfit_by_report' => true]);
                }
            }

            $shouldNotify = true;

            return $report->fresh();
        });

        $report->wasMergedIntoActiveReport = $wasMerged;

        if ($shouldNotify) {
            $recipients = app(NotificationRecipientResolver::class)
                ->damageAndStatusRecipients($report->equipment, $reporter->id);

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new DamageReportedNotification($report));
            }
        }

        return $report;
    }

    /**
     * Verify a pending unsafe report submitted by magang.
     * Confirming moves report to Open and equipment to Unfit; rejecting marks report Rejected with reason.
     */
    public function verifyUnsafeReport(DamageReport $report, bool $confirmUnsafe, User $verifier, ?string $rejectedReason = null): DamageReport
    {
        if (! $this->canManageReportVerification($verifier)) {
            throw ValidationException::withMessages([
                'status' => 'Hanya teknisi atau Admin IPSRS yang dapat memverifikasi laporan.',
            ]);
        }

        if (! $report->isPendingVerification()) {
            throw ValidationException::withMessages([
                'status' => 'Hanya laporan yang menunggu verifikasi yang dapat diproses.',
            ]);
        }

        if (! $confirmUnsafe) {
            Validator::make(['rejected_reason' => $rejectedReason], [
                'rejected_reason' => ['required', 'string', 'min:5', 'max:1000'],
            ], [], ['rejected_reason' => 'alasan penolakan'])->validate();
        }

        return DB::transaction(function () use ($report, $confirmUnsafe, $verifier, $rejectedReason): DamageReport {
            $locked = DamageReport::query()->whereKey($report->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPendingVerification()) {
                throw ValidationException::withMessages([
                    'status' => 'Laporan sudah diverifikasi sebelumnya.',
                ]);
            }

            if ($confirmUnsafe) {
                $equipment = Equipment::query()->whereKey($locked->equipment_id)->lockForUpdate()->firstOrFail();
                $history = $this->statusService->reportUnsafe(
                    $equipment,
                    $verifier->id,
                    'Verifikasi laporan tidak aman dipakai',
                );

                $locked->update([
                    'status' => DamageReportStatus::Open,
                    'verified_by' => $verifier->id,
                    'verified_at' => now(),
                    'marked_unfit_by_report' => $locked->marked_unfit_by_report || $history !== null,
                ]);
            } else {
                $locked->update([
                    'status' => DamageReportStatus::Rejected,
                    'verified_by' => $verifier->id,
                    'verified_at' => now(),
                    'rejected_reason' => trim((string) $rejectedReason),
                ]);
            }

            return $locked->fresh();
        });
    }

    /**
     * Close an open/pending report without creating a repair (e.g. operator error or false alarm).
     * Restores equipment status via calibration evaluation if this report marked it Unfit.
     */
    public function closeWithoutRepair(DamageReport $report, string $reason, User $actor): DamageReport
    {
        if (! $this->canManageReportVerification($actor)) {
            throw ValidationException::withMessages([
                'reason' => 'Hanya teknisi atau Admin IPSRS yang dapat menutup laporan tanpa perbaikan.',
            ]);
        }

        $validated = Validator::make(['reason' => $reason], [
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ], [], ['reason' => 'alasan penutupan'])->validate();

        $closed = DB::transaction(function () use ($report, $validated, $actor): DamageReport {
            $locked = DamageReport::query()->whereKey($report->id)->lockForUpdate()->firstOrFail();

            if (! $locked->canBeClosedWithoutRepair()) {
                throw ValidationException::withMessages([
                    'reason' => 'Laporan ini tidak dapat ditutup tanpa perbaikan karena sudah diproses atau selesai.',
                ]);
            }

            $locked->update([
                'status' => DamageReportStatus::Resolved,
                'closed_without_repair_reason' => trim($validated['reason']),
                'closed_by' => $actor->id,
                'closed_at' => now(),
            ]);

            if ($locked->marked_unfit_by_report) {
                $equipment = Equipment::query()->whereKey($locked->equipment_id)->lockForUpdate()->firstOrFail();

                if ($equipment->status === EquipmentStatus::Unfit) {
                    $this->statusService->restoreAfterCloseWithoutRepair(
                        $equipment,
                        $actor->id,
                        'Laporan ditutup tanpa perbaikan: '.trim($validated['reason']),
                    );
                }
            }

            return $locked->fresh('equipment.room');
        });

        if (! EquipmentStatusService::$suppressNotifications && $closed->equipment) {
            $roomHeads = app(NotificationRecipientResolver::class)->roomHeadsFor($closed->equipment);
            if ($roomHeads->isNotEmpty()) {
                Notification::send($roomHeads, new \App\Notifications\EquipmentOperationalNotification(
                    $closed->equipment,
                    \App\Notifications\EquipmentOperationalNotification::EVENT_CLOSED_WITHOUT_REPAIR,
                    $closed->closed_without_repair_reason,
                ));
            }
        }

        return $closed;
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
            'complaint' => ['required', 'string', 'min:10', 'max:2000'],
        ], [], [
            'complaint' => 'keluhan',
        ])->validate();

        $report->update([
            'complaint' => $validated['complaint'],
        ]);

        return $report->fresh();
    }

    public function canReportEquipment(User $user, Equipment $equipment): bool
    {
        if (! $user->can('Create:DamageReport')) {
            return false;
        }

        $isRoomHeadOnly = $user->hasRole(UserRole::KepalaRuangan->value)
            && ! $user->hasAnyRole([
                UserRole::SuperAdmin->value,
                UserRole::AdminIpsrs->value,
                UserRole::Teknisi->value,
            ]);

        if ($isRoomHeadOnly) {
            return $user->room_id !== null && (int) $user->room_id === (int) $equipment->room_id;
        }

        return true;
    }

    public function isMagangOnly(User $user): bool
    {
        return $user->hasRole(UserRole::Magang->value)
            && ! $user->hasAnyRole([
                UserRole::SuperAdmin->value,
                UserRole::AdminIpsrs->value,
                UserRole::Teknisi->value,
                UserRole::KepalaRuangan->value,
            ]);
    }

    public function canManageReportVerification(User $user): bool
    {
        return $user->hasAnyRole([
            UserRole::SuperAdmin->value,
            UserRole::AdminIpsrs->value,
            UserRole::Teknisi->value,
        ]);
    }

    private function countUnsafeReportsInLastHour(User $reporter): int
    {
        $since = now()->subHour();

        $reportsCount = DamageReport::query()
            ->where('reported_by', $reporter->id)
            ->where('is_safe_to_use', false)
            ->where('created_at', '>=', $since)
            ->count();

        $notesCount = DamageReportNote::query()
            ->where('reported_by', $reporter->id)
            ->where('is_safe_to_use', false)
            ->where('created_at', '>=', $since)
            ->count();

        return $reportsCount + $notesCount;
    }
}
