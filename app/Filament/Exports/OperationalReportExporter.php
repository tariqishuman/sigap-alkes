<?php

namespace App\Filament\Exports;

use App\Enums\EquipmentStatus;
use App\Models\Equipment;
use App\Services\UptimeCalculator;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Carbon;

class OperationalReportExporter extends Exporter
{
    protected static ?string $model = Equipment::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('inventory_code')->label('Kode Inventaris'),
            ExportColumn::make('name')->label('Nama Alat'),
            ExportColumn::make('equipmentType.name')->label('Jenis Alat'),
            ExportColumn::make('room.name')->label('Ruangan'),
            ExportColumn::make('status')
                ->label('Status')
                ->formatStateUsing(fn (EquipmentStatus $state): string => $state->getLabel()),
            ExportColumn::make('total_hours')
                ->label('Total Jam (T)')
                ->state(function (Equipment $record, array $options): string {
                    [$start, $end] = self::resolvePeriod($options);
                    $metrics = app(UptimeCalculator::class)->forEquipment($record, $start, $end);

                    return number_format($metrics['total_hours'], 2, '.', '');
                }),
            ExportColumn::make('downtime_hours')
                ->label('Downtime (D)')
                ->state(function (Equipment $record, array $options): string {
                    [$start, $end] = self::resolvePeriod($options);
                    $metrics = app(UptimeCalculator::class)->forEquipment($record, $start, $end);

                    return number_format($metrics['downtime_hours'], 2, '.', '');
                }),
            ExportColumn::make('uptime_percent')
                ->label('Uptime (%)')
                ->state(function (Equipment $record, array $options): string {
                    [$start, $end] = self::resolvePeriod($options);
                    $metrics = app(UptimeCalculator::class)->forEquipment($record, $start, $end);

                    return $metrics['uptime_percent'] !== null
                        ? number_format($metrics['uptime_percent'], 2, '.', '')
                        : '–';
                }),
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{0: Carbon, 1: Carbon}
     */
    private static function resolvePeriod(array $options): array
    {
        $monthStr = (string) ($options['month'] ?? today()->format('Y-m'));

        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthStr)) {
            $monthStr = today()->format('Y-m');
        }

        $month = Carbon::createFromFormat('!Y-m', $monthStr);

        return [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Ekspor laporan uptime selesai: '.number_format($export->successful_rows, 0, ',', '.').' baris.';

        if ($failedRows = $export->getFailedRowsCount()) {
            $body .= ' Gagal: '.number_format($failedRows, 0, ',', '.').' baris.';
        }

        return $body;
    }
}
