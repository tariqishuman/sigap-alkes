<?php

namespace App\Notifications;

use App\Enums\AlertTier;
use App\Filament\Resources\DamageReports\DamageReportResource;
use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

class UnsafeReportRateLimitNotification extends Notification
{
    public function __construct(
        public User $reporter,
        public int $countInLastHour,
    ) {}

    public function alertTier(): AlertTier
    {
        return AlertTier::Important;
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable instanceof User && $notifiable->shouldReceivePushForTier($this->alertTier())) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    /**
     * @return array<string, mixed>
     */
    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'Batas laporan tidak aman terlampaui',
            'body' => "{$this->reporter->name} mencoba mengirim lebih dari {$this->countInLastHour} laporan \"tidak aman dipakai\" dalam 1 jam terakhir.",
            'url' => DamageReportResource::getUrl('index'),
            'tag' => "rate-limit-{$this->reporter->id}",
            'requireInteraction' => false,
            'actions' => [
                ['action' => 'open', 'title' => 'Lihat laporan'],
            ],
        ];
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
