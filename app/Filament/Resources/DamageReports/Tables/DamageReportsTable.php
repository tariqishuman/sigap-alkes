<?php

namespace App\Filament\Resources\DamageReports\Tables;

use App\Enums\DamageReportStatus;
use App\Filament\Resources\DamageReports\DamageReportResource;
use App\Filament\Resources\Repairs\RepairResource;
use App\Models\DamageReport;
use App\Models\Room;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DamageReportsTable
{
    public static function configure(Table $table, bool $forEquipmentRelation = false): Table
    {
        $columns = [
            TextColumn::make('reported_at')
                ->label('Waktu Lapor')
                ->dateTime('d M Y H:i')
                ->sortable(),
        ];

        if (! $forEquipmentRelation) {
            $columns[] = TextColumn::make('equipment.inventory_code')
                ->label('Kode Inventaris')
                ->fontFamily('mono')
                ->searchable();
            $columns[] = TextColumn::make('equipment.name')
                ->label('Alat')
                ->searchable();
            $columns[] = TextColumn::make('equipment.room.name')
                ->label('Ruangan');
        }

        $columns = array_merge($columns, [
            TextColumn::make('complaint')
                ->label('Keluhan')
                ->limit(60)
                ->wrap()
                ->searchable(),
            TextColumn::make('reportedByUser.name')
                ->label('Pelapor')
                ->placeholder('-'),
            TextColumn::make('status')
                ->label('Status')
                ->badge()
                ->sortable(),
            TextColumn::make('repair.technician.name')
                ->label('Teknisi')
                ->placeholder('-')
                ->toggleable(isToggledHiddenByDefault: ! $forEquipmentRelation),
            TextColumn::make('repair_total_cost')
                ->label('Biaya Perbaikan')
                ->state(fn (DamageReport $record): string => $record->repair
                    ? 'Rp'.number_format((float) $record->repair->totalCost(), 0, ',', '.')
                    : '-')
                ->toggleable(isToggledHiddenByDefault: ! $forEquipmentRelation),
        ]);

        $filters = [
            SelectFilter::make('status')
                ->label('Status')
                ->options(DamageReportStatus::class),
        ];

        if (! $forEquipmentRelation) {
            $filters[] = SelectFilter::make('room_id')
                ->label('Ruangan')
                ->options(fn (): array => Room::query()->orderBy('name')->pluck('name', 'id')->all())
                ->query(function (Builder $query, array $data): Builder {
                    if (blank($data['value'] ?? null)) {
                        return $query;
                    }

                    return $query->whereHas('equipment', fn (Builder $q) => $q->where('room_id', $data['value']));
                });
        }

        return $table
            ->defaultSort('reported_at', 'desc')
            ->columns($columns)
            ->filters($filters)
            ->recordActions([
                Action::make('start_repair')
                    ->label('Mulai perbaikan')
                    ->icon(Heroicon::OutlinedWrenchScrewdriver)
                    ->color('primary')
                    ->visible(fn (DamageReport $record): bool => $record->isOpen()
                        && ($record->equipment?->is_active ?? false)
                        && RepairResource::canCreate())
                    ->url(fn (DamageReport $record): string => RepairResource::getUrl('create', ['damage_report_id' => $record->id])),
                Action::make('view_repair')
                    ->label('Lihat perbaikan')
                    ->icon(Heroicon::OutlinedEye)
                    ->visible(fn (DamageReport $record): bool => $record->repair !== null && RepairResource::canView($record->repair))
                    ->url(fn (DamageReport $record): string => RepairResource::getUrl('view', ['record' => $record->repair])),
                Action::make('edit')
                    ->label('Ubah')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->visible(fn (DamageReport $record): bool => DamageReportResource::canEdit($record))
                    ->url(fn (DamageReport $record): string => DamageReportResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
