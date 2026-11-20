<?php

namespace App\Notifications;

use App\Filament\Resources\DamageReports\DamageReportResource;
use App\Models\DamageReport;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

class DamageReportedNotification extends Notification
{
    public function __construct(public DamageReport $damageReport) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $this->damageReport->loadMissing(['equipment.room', 'reportedByUser']);

        $equipment = $this->damageReport->equipment;
        $roomName = $equipment?->room?->name ?? '-';
        $reporterName = $this->damageReport->reportedByUser?->name ?? 'Pengguna';

        if ($this->damageReport->isPendingVerification()) {
            $title = 'Verifikasi laporan tidak aman dipakai';
            $body = "{$equipment?->name} ({$equipment?->inventory_code}) di {$roomName} dilaporkan tidak aman dipakai oleh {$reporterName} (menunggu verifikasi).";
        } elseif (! $this->damageReport->is_safe_to_use) {
            $title = 'Alat dilaporkan tidak aman dipakai';
            $body = "{$equipment?->name} ({$equipment?->inventory_code}) di {$roomName} dilaporkan tidak aman dipakai oleh {$reporterName}.";
        } else {
            $title = 'Laporan kerusakan baru';
            $body = "Laporan kerusakan baru untuk {$equipment?->name} ({$equipment?->inventory_code}) di {$roomName} oleh {$reporterName}.";
        }

        $notification = FilamentNotification::make()
            ->title($title)
            ->body($body);

        if (! $this->damageReport->is_safe_to_use && ! $this->damageReport->isPendingVerification()) {
            $notification->danger();
        } else {
            $notification->warning();
        }

        return $notification
            ->actions([
                Action::make('view')
                    ->label('Lihat laporan')
                    ->url(DamageReportResource::getUrl('index')),
            ])
            ->getDatabaseMessage();
    }
}
