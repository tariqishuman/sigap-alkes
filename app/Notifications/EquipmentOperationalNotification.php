<?php

namespace App\Notifications;

use App\Enums\AlertTier;
use App\Filament\Resources\Equipment\EquipmentResource;
use App\Models\Equipment;
use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

class EquipmentOperationalNotification extends Notification
{
    public const EVENT_OVERDUE = 'overdue';

    public const EVENT_UNFIT = 'unfit';

    public const EVENT_REPAIR_STARTED = 'repair_started';

    public const EVENT_REPAIR_FINISHED = 'repair_finished';

    public const EVENT_FIT_FOR_USE = 'fit_for_use';

    public const EVENT_CLOSED_WITHOUT_REPAIR = 'closed_without_repair';

    public function __construct(
        public Equipment $equipment,
        public string $event,
        public ?string $detail = null,
    ) {}

    public function alertTier(): AlertTier
    {
        if (in_array($this->event, [self::EVENT_OVERDUE, self::EVENT_UNFIT], true)) {
            return $this->equipment->isCritical() ? AlertTier::Critical : AlertTier::Important;
        }

        return AlertTier::Info;
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
            'title' => $this->title(),
            'body' => $this->body(),
            'url' => $this->actionUrl(),
            'tag' => "equipment-{$this->equipment->id}-{$this->event}",
            'requireInteraction' => $this->alertTier() === AlertTier::Critical,
            'actions' => [
                ['action' => 'open', 'title' => 'Lihat alat'],
            ],
        ];
    }

    public function title(): string
    {
        return match ($this->event) {
            self::EVENT_OVERDUE => 'Alat terlambat kalibrasi',
            self::EVENT_UNFIT => 'Alat tidak laik pakai',
            self::EVENT_REPAIR_STARTED => 'Perbaikan alat dimulai',
            self::EVENT_REPAIR_FINISHED => 'Perbaikan alat selesai',
            self::EVENT_FIT_FOR_USE => 'Alat kembali laik pakai',
            self::EVENT_CLOSED_WITHOUT_REPAIR => 'Laporan kerusakan ditutup',
            default => 'Pembaruan status alat',
        };
    }

    /**
     * Single-sentence notification body without cost or patient data.
     */
    public function body(): string
    {
        $this->equipment->loadMissing('room');
        $name = $this->equipment->name;
        $code = $this->equipment->inventory_code;
        $room = $this->equipment->room?->name ?? 'Ruangan';

        return match ($this->event) {
            self::EVENT_OVERDUE => "{$name} ({$code}) di {$room} berstatus Terlambat kalibrasi dan tidak boleh digunakan.",
            self::EVENT_UNFIT => "{$name} ({$code}) di {$room} berstatus Tidak laik pakai. Hentikan penggunaan dan koordinasikan dengan IPSRS.",
            self::EVENT_REPAIR_STARTED => "Perbaikan {$name} ({$code}) di {$room} telah dimulai oleh teknisi IPSRS.",
            self::EVENT_REPAIR_FINISHED => "Perbaikan {$name} ({$code}) di {$room} telah selesai.",
            self::EVENT_FIT_FOR_USE => "{$name} ({$code}) di {$room} kembali berstatus Laik pakai.",
            self::EVENT_CLOSED_WITHOUT_REPAIR => "Laporan kerusakan {$name} ({$code}) di {$room} ditutup tanpa perbaikan".($this->detail ? " ({$this->detail})." : '.'),
            default => "Status {$name} ({$code}) di {$room} telah diperbarui.",
        };
    }

    public function actionUrl(): string
    {
        return EquipmentResource::getUrl('view', ['record' => $this->equipment]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $notification = FilamentNotification::make()
            ->title($this->title())
            ->body($this->body());

        match ($this->event) {
            self::EVENT_OVERDUE, self::EVENT_UNFIT => $notification->danger(),
            self::EVENT_FIT_FOR_USE, self::EVENT_REPAIR_FINISHED, self::EVENT_CLOSED_WITHOUT_REPAIR => $notification->success(),
            default => $notification->info(),
        };

        return $notification
            ->actions([
                Action::make('view')
                    ->label('Lihat alat')
                    ->url($this->actionUrl()),
            ])
            ->getDatabaseMessage();
    }
}
