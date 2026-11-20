<?php

namespace App\Notifications;

use App\Filament\Resources\SpareParts\SparePartResource;
use App\Models\SparePart;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

class LowSparePartStockNotification extends Notification
{
    public function __construct(public SparePart $sparePart) {}

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
        $body = "Stok {$this->sparePart->name} menipis (tersisa {$this->sparePart->stock} {$this->sparePart->unit}, batas minimum {$this->sparePart->min_stock} {$this->sparePart->unit}).";

        return FilamentNotification::make()
            ->title('Stok suku cadang menipis')
            ->body($body)
            ->warning()
            ->actions([
                Action::make('view')
                    ->label('Lihat suku cadang')
                    ->url(SparePartResource::getUrl('index')),
            ])
            ->getDatabaseMessage();
    }
}
