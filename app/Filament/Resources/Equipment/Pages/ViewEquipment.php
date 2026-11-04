<?php

namespace App\Filament\Resources\Equipment\Pages;

use App\Filament\Resources\Equipment\EquipmentResource;
use App\Models\Equipment;
use App\Services\QrLabelService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ViewEquipment extends ViewRecord
{
    protected static string $resource = EquipmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print_qr')
                ->label('Cetak label QR')
                ->icon(Heroicon::OutlinedQrCode)
                ->color('primary')
                ->visible(fn (): bool => Auth::user()?->can('Update:Equipment') ?? false)
                ->action(fn (Equipment $record) => app(QrLabelService::class)->downloadLabelsPdf(
                    collect([$record]),
                    "label-qr-{$record->inventory_code}.pdf",
                )),
            EditAction::make()
                ->label('Ubah'),
        ];
    }
}
