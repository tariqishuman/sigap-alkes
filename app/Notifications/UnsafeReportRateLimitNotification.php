<?php

namespace App\Notifications;

use App\Filament\Resources\DamageReports\DamageReportResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

class UnsafeReportRateLimitNotification extends Notification
{
    public function __construct(
        public User $reporter,
        public int $countInLastHour,
    ) {}

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
        $body = "{$this->reporter->name} mencoba mengirim lebih dari {$this->countInLastHour} laporan \"tidak aman dipakai\" dalam 1 jam terakhir. Mohon periksa aktivitas pelaporan.";

        return FilamentNotification::make()
            ->title('Batas laporan tidak aman terlampaui')
            ->body($body)
            ->danger()
            ->actions([
                Action::make('view')
                    ->label('Lihat laporan kerusakan')
                    ->url(DamageReportResource::getUrl('index')),
            ])
            ->getDatabaseMessage();
    }
}
