<?php

namespace App\Filament\Resources\Equipment\Pages;

use App\Enums\EquipmentStatus;
use App\Filament\Resources\Calibrations\CalibrationResource;
use App\Filament\Resources\Equipment\EquipmentResource;
use App\Models\Equipment;
use App\Services\EquipmentStatusService;
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
            Action::make('start_testing')
                ->label('Mulai uji & kalibrasi')
                ->icon(Heroicon::OutlinedBeaker)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Mulai uji & kalibrasi')
                ->modalDescription('Status alat akan berubah menjadi "Dalam uji & kalibrasi".')
                ->visible(fn (Equipment $record): bool => $record->is_active
                    && $record->status !== EquipmentStatus::UnderTesting
                    && in_array($record->status, EquipmentStatus::CALIBRATABLE, true)
                    && CalibrationResource::canCreate())
                ->action(function (Equipment $record) {
                    app(EquipmentStatusService::class)->startTesting($record, Auth::id());

                    return redirect(static::getResource()::getUrl('view', ['record' => $record]));
                }),
            Action::make('record_calibration')
                ->label('Catat hasil kalibrasi')
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->visible(fn (Equipment $record): bool => $record->is_active
                    && in_array($record->status, EquipmentStatus::CALIBRATABLE, true)
                    && CalibrationResource::canCreate())
                ->url(fn (Equipment $record): string => CalibrationResource::getUrl('create', ['equipment_id' => $record->id])),
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
