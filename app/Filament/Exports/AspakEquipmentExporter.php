<?php

namespace App\Filament\Exports;

use App\Enums\EquipmentStatus;
use App\Models\Equipment;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

/**
 * Temporary ASPAK export format (PRD §9 & docs/assumptions.md).
 * Column layout will be adjusted after field observation with the partner hospital.
 */
class AspakEquipmentExporter extends Exporter
{
    protected static ?string $model = Equipment::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('inventory_code')->label('Kode Inventaris'),
            ExportColumn::make('name')->label('Nama Alat'),
            ExportColumn::make('equipmentType.name')->label('Jenis'),
            ExportColumn::make('brand')->label('Merk'),
            ExportColumn::make('model')->label('Tipe / Model'),
            ExportColumn::make('serial_number')->label('Nomor Seri'),
            ExportColumn::make('room.name')->label('Ruangan'),
            ExportColumn::make('acquisition_date')
                ->label('Tahun Perolehan')
                ->formatStateUsing(fn ($state): ?string => $state?->format('Y')),
            ExportColumn::make('acquisition_cost')
                ->label('Harga Perolehan')
                ->formatStateUsing(fn ($state): string => bcadd((string) ($state ?? '0'), '0', 2)),
            ExportColumn::make('status')
                ->label('Kondisi')
                ->formatStateUsing(fn (EquipmentStatus $state): string => $state->getLabel()),
            ExportColumn::make('latestValidCalibration.tested_at')
                ->label('Tanggal Kalibrasi Terakhir')
                ->formatStateUsing(fn ($state): ?string => $state?->format('d/m/Y')),
            ExportColumn::make('latestValidCalibration.valid_until')
                ->label('Berlaku s.d.')
                ->formatStateUsing(fn ($state): ?string => $state?->format('d/m/Y')),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Ekspor ASPAK (format kolom sementara) selesai: '.number_format($export->successful_rows, 0, ',', '.').' baris.';

        if ($failedRows = $export->getFailedRowsCount()) {
            $body .= ' Gagal: '.number_format($failedRows, 0, ',', '.').' baris.';
        }

        return $body;
    }
}
