<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Equipment;
use App\Models\SparePart;
use App\Models\User;
use App\Notifications\DailyInfoDigestNotification;
use App\Services\SettingsService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Notification;

#[Signature('notifications:send-daily-digest')]
#[Description('Kirim email ringkasan harian untuk notifikasi tingkat Info')]
class SendDailyInfoDigest extends Command
{
    public function handle(SettingsService $settings): int
    {
        if (! (bool) $settings->get('reminder.email_enabled', true)) {
            $this->info('Email pengingat dinonaktifkan di pengaturan.');

            return self::SUCCESS;
        }

        $lines = [];

        // 1. Kalibrasi H-30 (dalam 8..30 hari)
        $calDue30 = Equipment::query()
            ->where('is_active', true)
            ->whereHas('equipmentType', fn (Builder $q) => $q->where('requires_calibration', true))
            ->dueBetween(today()->addDays(8), today()->addDays(30))
            ->count();
        if ($calDue30 > 0) {
            $lines[] = "{$calDue30} alat memiliki sertifikat kalibrasi jatuh tempo dalam 8–30 hari ke depan.";
        }

        // 2. PM H-7 (dalam 1..7 hari)
        $pmDue7 = Equipment::query()
            ->where('is_active', true)
            ->whereHas('equipmentType', fn (Builder $q) => $q->whereNotNull('pm_interval_months'))
            ->whereHas('latestPreventiveMaintenance', fn (Builder $q) => $q->whereBetween('next_due_at', [today()->addDay(), today()->addDays(7)]))
            ->count();
        if ($pmDue7 > 0) {
            $lines[] = "{$pmDue7} alat terjadwal pemeliharaan preventif dalam 7 hari ke depan.";
        }

        // 3. Stok suku cadang menipis
        $lowStock = SparePart::query()
            ->whereColumn('stock_quantity', '<=', 'minimum_stock')
            ->count();
        if ($lowStock > 0) {
            $lines[] = "{$lowStock} jenis suku cadang berada di bawah stok minimum.";
        }

        if ($lines === []) {
            $this->info('Tidak ada item info harian untuk dikirim.');

            return self::SUCCESS;
        }

        $recipients = User::role([UserRole::Teknisi->value, UserRole::AdminIpsrs->value])->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new DailyInfoDigestNotification($lines));
        }

        $this->info('Ringkasan harian Info berhasil dikirim.');

        return self::SUCCESS;
    }
}
