<?php

namespace App\Notifications;

use App\Enums\AlertTier;
use App\Filament\Resources\Equipment\EquipmentResource;
use App\Models\Equipment;
use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use App\Services\SettingsService;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CalibrationDueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Equipment $equipment,
        public CarbonInterface $validUntil,
        public int $daysLeft,
    ) {}

    public function alertTier(): AlertTier
    {
        return $this->daysLeft <= 7 ? AlertTier::Important : AlertTier::Info;
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (app(SettingsService::class)->get('reminder.email_enabled', true)) {
            $channels[] = 'mail';
        }

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
            'title' => 'Pengingat kalibrasi',
            'body' => $this->message(),
            'url' => $this->equipmentUrl(),
            'tag' => "calibration-due-{$this->equipment->id}-{$this->validUntil->format('Ymd')}",
            'requireInteraction' => false,
            'actions' => [
                ['action' => 'open', 'title' => 'Lihat alat'],
            ],
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Pengingat kalibrasi')
            ->body($this->message())
            ->warning()
            ->actions([
                Action::make('view')
                    ->label('Lihat alat')
                    ->url($this->equipmentUrl()),
            ])
            ->getDatabaseMessage();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $settings = app(SettingsService::class);

        return (new MailMessage)
            ->subject("Pengingat kalibrasi: {$this->equipment->name}")
            ->greeting('Halo,')
            ->line($this->message())
            ->action('Lihat alat', $this->equipmentUrl())
            ->salutation('Salam, '.$settings->appName().' · '.$settings->hospitalName());
    }

    public function message(): string
    {
        $equipment = "{$this->equipment->name} ({$this->equipment->inventory_code})";
        $date = $this->validUntil->translatedFormat('d F Y');

        return match (true) {
            $this->daysLeft > 0 => "Kalibrasi {$equipment} jatuh tempo {$this->daysLeft} hari lagi ({$date}).",
            $this->daysLeft === 0 => "Kalibrasi {$equipment} jatuh tempo hari ini ({$date}).",
            default => 'Kalibrasi '.$equipment.' sudah lewat jatuh tempo '.abs($this->daysLeft)." hari ({$date}).",
        };
    }

    private function equipmentUrl(): string
    {
        return EquipmentResource::getUrl('view', ['record' => $this->equipment]);
    }
}
