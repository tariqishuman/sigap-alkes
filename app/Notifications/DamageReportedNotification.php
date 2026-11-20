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

        $body = "Laporan kerusakan baru untuk {$equipment?->name} ({$equipment?->inventory_code}) di {$roomName} oleh {$reporterName}.";

        return FilamentNotification::make()
            ->title('Laporan kerusakan baru')
            ->body($body)
            ->warning()
            ->actions([
                Action::make('view')
                    ->label('Lihat laporan')
                    ->url(DamageReportResource::getUrl('index')),
            ])
            ->getDatabaseMessage();
    }
}
