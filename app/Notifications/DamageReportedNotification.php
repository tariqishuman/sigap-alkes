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
use Illuminate\Support\Facades\URL;

class DamageReportedNotification extends Notification
{
    public function __construct(public DamageReport $damageReport) {}

    public function alertTier(): AlertTier
    {
        $this->damageReport->loadMissing(['equipment.equipmentType', 'equipment.room']);

        if (
            ! $this->damageReport->is_safe_to_use
            && ! $this->damageReport->isPendingVerification()
            && ($this->damageReport->equipment?->isCritical() ?? false)
        ) {
            return AlertTier::Critical;
        }

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

    public function title(): string
    {
        if ($this->damageReport->isPendingVerification()) {
            return 'Verifikasi laporan tidak aman dipakai';
        }

        if (! $this->damageReport->is_safe_to_use) {
            return 'Alat dilaporkan tidak aman dipakai';
        }

        return 'Laporan kerusakan baru';
    }

    public function body(): string
    {
        $this->damageReport->loadMissing(['equipment.room', 'reportedByUser']);

        $equipment = $this->damageReport->equipment;
        $roomName = $equipment?->room?->name ?? '-';
        $reporterName = $this->damageReport->reportedByUser?->name ?? 'Pengguna';

        if ($this->damageReport->isPendingVerification()) {
            return "{$equipment?->name} ({$equipment?->inventory_code}) di {$roomName} dilaporkan tidak aman dipakai oleh {$reporterName} (menunggu verifikasi).";
        }

        if (! $this->damageReport->is_safe_to_use) {
            return "⚠️ {$equipment?->name} ({$equipment?->inventory_code}) di {$roomName} dilaporkan tidak aman dipakai.";
        }

        return "Laporan kerusakan baru untuk {$equipment?->name} ({$equipment?->inventory_code}) di {$roomName} oleh {$reporterName}.";
    }

    public function ackUrl(object $notifiable): string
    {
        if ($notifiable instanceof User) {
            return URL::temporarySignedRoute(
                'damage-reports.acknowledge.confirm',
                now()->addHours(24),
                ['damageReport' => $this->damageReport->id, 'user' => $notifiable->id],
            );
        }

        return DamageReportResource::getUrl('index');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $notification = FilamentNotification::make()
            ->title($this->title())
            ->body($this->body());

        if (! $this->damageReport->is_safe_to_use && ! $this->damageReport->isPendingVerification()) {
            $notification->danger();
        } else {
            $notification->warning();
        }

        return $notification
            ->actions([
                Action::make('ack')
                    ->label('Saya tangani')
                    ->url($this->ackUrl($notifiable)),
                Action::make('view')
                    ->label('Lihat laporan')
                    ->url(DamageReportResource::getUrl('index')),
            ])
            ->getDatabaseMessage();
    }

    /**
     * @return array<string, mixed>
     */
    public function toWebPush(object $notifiable): array
    {
        $isCritical = $this->alertTier() === AlertTier::Critical;

        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'url' => DamageReportResource::getUrl('index'),
            'ackUrl' => $this->ackUrl($notifiable),
            'tag' => "damage-report-{$this->damageReport->id}",
            'requireInteraction' => $isCritical,
            'actions' => [
                ['action' => 'ack', 'title' => 'Saya tangani'],
                ['action' => 'open', 'title' => 'Lihat laporan'],
            ],
        ];
    }
}
