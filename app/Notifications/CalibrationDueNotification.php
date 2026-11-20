<?php

namespace App\Notifications;

use App\Filament\Resources\Equipment\EquipmentResource;
use App\Models\Equipment;
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

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (app(SettingsService::class)->get('reminder.email_enabled', true)) {
            $channels[] = 'mail';
        }

        return $channels;
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
