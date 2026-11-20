<?php

namespace App\Filament\Resources\Repairs\Tables;

use App\Filament\Resources\Repairs\RepairResource;
use App\Models\Repair;
use App\Services\RepairService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class RepairsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('started_at', 'desc')
            ->columns([
                TextColumn::make('started_at')
                    ->label('Waktu Mulai')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('finished_at')
                    ->label('Waktu Selesai')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Berlangsung')
                    ->sortable(),
                TextColumn::make('damageReport.equipment.inventory_code')
                    ->label('Kode Inventaris')
                    ->fontFamily('mono')
                    ->searchable(),
                TextColumn::make('damageReport.equipment.name')
                    ->label('Alat')
                    ->searchable(),
                TextColumn::make('technician.name')
                    ->label('Teknisi')
                    ->placeholder('-'),
                TextColumn::make('damageReport.status')
                    ->label('Status Laporan')
                    ->badge(),
                TextColumn::make('total_cost')
                    ->label('Total Biaya')
                    ->state(fn (Repair $record): string => 'Rp'.number_format((float) $record->totalCost(), 0, ',', '.')),
            ])
            ->filters([
                TernaryFilter::make('is_finished')
                    ->label('Status Perbaikan')
                    ->placeholder('Semua')
                    ->trueLabel('Sudah selesai')
                    ->falseLabel('Sedang berlangsung')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('finished_at'),
                        false: fn (Builder $query): Builder => $query->whereNull('finished_at'),
                    ),
            ])
            ->recordActions([
                Action::make('finish_repair')
                    ->label('Tandai perbaikan selesai')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (Repair $record): bool => ! $record->isFinished() && RepairResource::canEdit($record))
                    ->fillForm(fn (Repair $record): array => [
                        'finished_at' => now(),
                        'action' => $record->action,
                    ])
                    ->form([
                        DateTimePicker::make('finished_at')
                            ->label('Waktu Selesai')
                            ->seconds(false)
                            ->required(),
                        Textarea::make('action')
                            ->label('Tindakan Perbaikan')
                            ->rows(4)
                            ->required(),
                    ])
                    ->action(function (Repair $record, array $data): void {
                        app(RepairService::class)->finish($record, $data, Auth::id());
                    }),
                ViewAction::make()
                    ->label('Detail'),
                Action::make('edit')
                    ->label('Ubah')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->visible(fn (Repair $record): bool => RepairResource::canEdit($record))
                    ->url(fn (Repair $record): string => RepairResource::getUrl('edit', ['record' => $record])),
            ])
            ->emptyState(view('filament.tables.empty-state', [
                'image' => 'empty-repair',
                'title' => 'Tidak ada kerusakan. Semua alat aman.',
                'description' => 'Belum ada catatan perbaikan yang berlangsung atau selesai.',
            ]));
    }
}
