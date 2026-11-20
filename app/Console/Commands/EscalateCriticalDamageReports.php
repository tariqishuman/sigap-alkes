<?php

namespace App\Console\Commands;

use App\Enums\DamageReportStatus;
use App\Enums\UserRole;
use App\Models\DamageReport;
use App\Models\User;
use App\Notifications\CriticalReportEscalationNotification;
use App\Services\SettingsService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Notification;

#[Signature('damage-reports:escalate-critical')]
#[Description('Eskalasi laporan kerusakan kritis yang belum ditangani melewati batas waktu')]
class EscalateCriticalDamageReports extends Command
{
    public function handle(SettingsService $settings): int
    {
        $minutes = max(1, (int) $settings->get('reminder.critical_escalation_minutes', 15));
        $cutoff = now()->subMinutes($minutes);

        $reports = DamageReport::query()
            ->where('status', DamageReportStatus::Open)
            ->where('is_safe_to_use', false)
            ->whereNull('acknowledged_by')
            ->whereNull('escalated_at')
            ->where('reported_at', '<=', $cutoff)
            ->whereHas('equipment', function (Builder $eq): void {
                $eq->where('is_active', true)
                    ->where(function (Builder $crit): void {
                        $crit->whereHas('equipmentType', fn (Builder $t) => $t->where('is_critical', true))
                            ->orWhereHas('room', fn (Builder $r) => $r->where('is_critical_area', true));
                    });
            })
            ->with(['equipment.room', 'equipment.equipmentType'])
            ->get();

        if ($reports->isEmpty()) {
            return self::SUCCESS;
        }

        $recipients = User::role([
            UserRole::AdminIpsrs->value,
            UserRole::Manajemen->value,
        ])->get()->unique('id');

        $escalatedCount = 0;

        foreach ($reports as $report) {
            $updated = DamageReport::query()
                ->whereKey($report->id)
                ->whereNull('acknowledged_by')
                ->whereNull('escalated_at')
                ->update(['escalated_at' => now()]);

            if ($updated !== 1) {
                continue;
            }

            $elapsed = max($minutes, (int) $report->reported_at->diffInMinutes(now()));

            activity()
                ->performedOn($report)
                ->event('escalated')
                ->withProperties(['elapsed_minutes' => $elapsed])
                ->log("Eskalasi otomatis laporan kritis setelah {$elapsed} menit belum ditangani");

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new CriticalReportEscalationNotification($report->fresh(['equipment.room']), $elapsed));
            }

            $escalatedCount++;
        }

        $this->info("Eskalasi selesai: {$escalatedCount} laporan dieskalasi.");

        return self::SUCCESS;
    }
}
