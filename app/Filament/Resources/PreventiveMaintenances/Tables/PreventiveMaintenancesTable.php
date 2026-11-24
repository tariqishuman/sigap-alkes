<?php

namespace App\Filament\Resources\PreventiveMaintenances\Tables;

use App\Enums\PreventiveMaintenanceResult;
use App\Filament\Resources\DamageReports\DamageReportResource;
use App\Models\PreventiveMaintenance;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PreventiveMaintenancesTable
{
    public static function configure(Table $table, bool $forEquipmentRelation = false): Table
    {
        $columns = [
            TextColumn::make('performed_at')
                ->label('Tanggal PM')
                ->date('d M Y')
                ->sortable(),
        ];

        if (! $forEquipmentRelation) {
            $columns[] = TextColumn::make('equipment.name')
                ->label('Alat')
                ->weight('medium')
                ->searchable(['name', 'inventory_code'])
                ->description(fn (PreventiveMaintenance $record): string => sprintf(
                    '%s • %s',
                    $record->equipment?->inventory_code ?? '-',
                    $record->equipment?->room?->name ?? '-',
                ));
        }

        $columns = array_merge($columns, [
            TextColumn::make('result')
                ->label('Hasil')
                ->badge()
                ->sortable(),
            TextColumn::make('recorded_via')
                ->label('Sumber')
                ->badge()
                ->formatStateUsing(fn (?string $state): string => $state === 'qr_scan' ? 'via scan QR' : 'Panel')
                ->color(fn (?string $state): string => $state === 'qr_scan' ? 'primary' : 'gray'),
            TextColumn::make('next_due_at')
                ->label('Jadwal Berikutnya')
                ->date('d M Y')
                ->placeholder('-')
                ->sortable(),
            TextColumn::make('performedByUser.name')
                ->label('Petugas')
                ->placeholder('-'),
            TextColumn::make('notes')
                ->label('Catatan')
                ->limit(40)
                ->placeholder('-'),
        ]);

        return $table
            ->defaultSort('performed_at', 'desc')
            ->columns($columns)
            ->filters([
                SelectFilter::make('result')
                    ->label('Hasil')
                    ->options(PreventiveMaintenanceResult::class),
            ])
            ->recordActions([
                Action::make('create_damage_report')
                    ->label('Buat laporan kerusakan')
                    ->icon(Heroicon::OutlinedExclamationTriangle)
                    ->color('warning')
                    ->visible(fn (PreventiveMaintenance $record): bool => $record->hasUnhealthyItems()
                        && ($record->equipment?->is_active ?? false)
                        && DamageReportResource::canCreate())
                    ->url(fn (PreventiveMaintenance $record): string => DamageReportResource::getUrl('create', [
                        'equipment_id' => $record->equipment_id,
                        'complaint' => $record->unhealthyItemsSummary(),
                    ])),
            ])
            ->emptyState(view('filament.tables.empty-state', [
                'image' => 'empty-calibration',
                'title' => 'Belum ada catatan pemeliharaan',
                'description' => 'Riwayat pemeliharaan preventif (PM) alat akan tampil di sini.',
            ]));
    }
}
