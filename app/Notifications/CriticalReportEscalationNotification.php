<?php

namespace App\Notifications;

use App\Enums\AlertTier;
use App\Filament\Resources\DamageReports\DamageReportResource;
use App\Models\DamageReport;
use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

class CriticalReportEscalationNotification extends Notification
{
    public function __construct(
        public DamageReport $damageReport,
        public int $elapsedMinutes,
    ) {}

    public function alertTier(): AlertTier
    {
        return AlertTier::Critical;
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable instanceof User && $notifiable->shouldReceivePushForTier($this->alertTier(), isCriticalEscalation: true)) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    public function title(): string
    {
        return 'Eskalasi: laporan kritis belum ditangani';
    }

    public function body(): string
    {
        $this->damageReport->loadMissing('equipment.room');
        $eq = $this->damageReport->equipment;
        $room = $eq?->room?->name ?? 'Ruangan';

        return "⚠️ {$eq?->name} ({$eq?->inventory_code}) di {$room} dilaporkan tidak aman dipakai dan belum ditangani selama {$this->elapsedMinutes} menit.";
    }

    public function ackUrl(): string
    {
        return DamageReportResource::getUrl('index', ['ack' => $this->damageReport->id]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->title())
            ->body($this->body())
            ->danger()
            ->actions([
                Action::make('ack')
                    ->label('Saya tangani')
                    ->url($this->ackUrl()),
            ])
            ->getDatabaseMessage();
    }

    /**
     * @return array<string, mixed>
     */
    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'url' => DamageReportResource::getUrl('index'),
            'ackUrl' => $this->ackUrl(),
            'tag' => "escalation-damage-{$this->damageReport->id}",
            'requireInteraction' => true,
            'actions' => [
                ['action' => 'ack', 'title' => 'Saya tangani'],
                ['action' => 'open', 'title' => 'Buka'],
            ],
        ];
    }
}
