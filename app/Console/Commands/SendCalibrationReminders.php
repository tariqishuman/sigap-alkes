<?php

namespace App\Console\Commands;

use App\Enums\EquipmentStatus;
use App\Enums\ReminderKind;
use App\Enums\UserRole;
use App\Models\CalibrationReminder;
use App\Models\Equipment;
use App\Models\User;
use App\Notifications\CalibrationDueNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

#[Signature('calibration:send-reminders')]
#[Description('Kirim pengingat kalibrasi H-30, H-7, dan H-0')]
class SendCalibrationReminders extends Command
{
    public function handle(): int
    {
        $recipients = User::role([UserRole::Teknisi->value, UserRole::AdminIpsrs->value])->get();

        // Stop before recording anything, so the reminders are not lost once a recipient exists.
        if ($recipients->isEmpty()) {
            $this->error('Tidak ada teknisi atau admin IPSRS untuk menerima pengingat.');

            return self::FAILURE;
        }

        Equipment::query()
            ->where('is_active', true)
            ->whereHas('equipmentType', fn ($query) => $query->where('requires_calibration', true))
            // Equipment under testing or repair, or already unfit, is being handled.
            ->whereIn('status', [EquipmentStatus::FitForUse, EquipmentStatus::CalibrationDue, EquipmentStatus::Overdue])
            ->with('latestValidCalibration')
            ->chunkById(100, function ($equipmentList) use ($recipients) {
                $equipmentList->each(fn (Equipment $equipment) => $this->remind($equipment, $recipients));
            });

        $this->info('Pengingat kalibrasi sudah diproses.');

        return self::SUCCESS;
    }

    private function remind(Equipment $equipment, Collection $recipients): void
    {
        $validUntil = $equipment->latestValidCalibration?->valid_until;

        if (! $validUntil) {
            return;
        }

        $daysLeft = (int) today()->diffInDays($validUntil, false);
        $kind = ReminderKind::forDaysLeft($daysLeft);

        if (! $kind) {
            return;
        }

        // The reminder row and the queued notifications succeed or fail together.
        DB::transaction(function () use ($equipment, $validUntil, $daysLeft, $kind, $recipients) {
            $reminder = CalibrationReminder::firstOrCreate(
                ['equipment_id' => $equipment->id, 'kind' => $kind, 'cycle_valid_until' => $validUntil],
                ['sent_at' => now(), 'channel' => 'database,mail'],
            );

            if ($reminder->wasRecentlyCreated) {
                Notification::send($recipients, new CalibrationDueNotification($equipment, $validUntil, $daysLeft));
            }
        });
    }
}
