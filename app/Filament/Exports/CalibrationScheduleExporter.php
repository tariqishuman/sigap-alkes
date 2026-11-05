<?php

namespace App\Filament\Exports;

use App\Enums\EquipmentStatus;
use App\Models\Equipment;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class CalibrationScheduleExporter extends Exporter
{
    protected static ?string $model = Equipment::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('inventory_code')->label('Kode Inventaris'),
            ExportColumn::make('name')->label('Nama Alat'),
            ExportColumn::make('equipmentType.name')->label('Jenis Alat'),
            ExportColumn::make('room.name')->label('Ruangan'),
            ExportColumn::make('brand')->label('Merek'),
            ExportColumn::make('model')->label('Tipe / Model'),
            ExportColumn::make('serial_number')->label('Nomor Seri'),
            ExportColumn::make('status')
                ->label('Status')
                ->formatStateUsing(fn (EquipmentStatus $state): string => $state->getLabel()),
            ExportColumn::make('latestValidCalibration.certificate_number')->label('Sertifikat Terakhir'),
            ExportColumn::make('latestValidCalibration.valid_until')
                ->label('Jatuh Tempo')
                ->formatStateUsing(fn ($state): ?string => $state?->format('d/m/Y')),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Ekspor jadwal kalibrasi selesai: '.number_format($export->successful_rows, 0, ',', '.').' baris.';

        if ($failedRows = $export->getFailedRowsCount()) {
            $body .= ' Gagal: '.number_format($failedRows, 0, ',', '.').' baris.';
        }

        return $body;
    }
}
