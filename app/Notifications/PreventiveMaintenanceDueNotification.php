<?php

namespace App\Notifications;

use App\Filament\Pages\CalibrationSchedule;
use App\Models\Equipment;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

class PreventiveMaintenanceDueNotification extends Notification
{
    public function __construct(
        public Equipment $equipment,
        public CarbonInterface $dueDate,
        public int $daysLeft,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function title(): string
    {
        return $this->daysLeft <= 0
            ? 'Pemeliharaan preventif jatuh tempo'
            : 'Pengingat pemeliharaan preventif';
    }

    public function body(): string
    {
        $this->equipment->loadMissing('room');
        $name = $this->equipment->name;
        $code = $this->equipment->inventory_code;
        $room = $this->equipment->room?->name ?? 'Ruangan';
        $due = $this->dueDate->translatedFormat('d M Y');

        if ($this->daysLeft < 0) {
            return "Pemeliharaan preventif {$name} ({$code}) di {$room} sudah lewat ".abs($this->daysLeft)." hari (jadwal {$due}).";
        }

        if ($this->daysLeft === 0) {
            return "Pemeliharaan preventif {$name} ({$code}) di {$room} jatuh tempo hari ini ({$due}).";
        }

        return "Pemeliharaan preventif {$name} ({$code}) di {$room} jatuh tempo dalam {$this->daysLeft} hari ({$due}).";
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $notification = FilamentNotification::make()
            ->title($this->title())
            ->body($this->body());

        if ($this->daysLeft <= 0) {
            $notification->warning();
        } else {
            $notification->info();
        }

        return $notification
            ->actions([
                Action::make('view')
                    ->label('Buka jadwal')
                    ->url(CalibrationSchedule::getUrl()),
            ])
            ->getDatabaseMessage();
    }
}
