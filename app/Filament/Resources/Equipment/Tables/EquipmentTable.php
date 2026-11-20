<?php

namespace App\Filament\Resources\Equipment\Tables;

use App\Enums\EquipmentStatus;
use App\Filament\Resources\Equipment\EquipmentResource;
use App\Models\Equipment;
use App\Services\QrLabelService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class EquipmentTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(fn (Equipment $record): ?string => EquipmentResource::canView($record)
                ? EquipmentResource::getUrl('view', ['record' => $record])
                : null)
            ->columns([
                ImageColumn::make('photo_path')
                    ->label('Foto')
                    ->disk('public')
                    ->defaultImageUrl(url('/images/equipment-placeholder.svg'))
                    ->square()
                    ->imageSize(38),
                TextColumn::make('name')
                    ->label('Alat')
                    ->searchable(['name', 'inventory_code'])
                    ->sortable()
                    ->weight('medium')
                    ->limit(32)
                    ->description(fn (Equipment $record): string => sprintf(
                        '%s • %s',
                        $record->inventory_code,
                        $record->equipmentType?->name ?? '-',
                    )),
                TextColumn::make('room.name')
                    ->label('Ruangan')
                    ->sortable(),
                ViewColumn::make('status')
                    ->label('Status')
                    ->view('components.sigap.table-status-badge')
                    ->sortable(),
                ViewColumn::make('valid_until_display')
                    ->label('Berlaku s.d.')
                    ->view('components.sigap.table-valid-until'),
                TextColumn::make('book_value')
                    ->label('Nilai buku')
                    ->visible(fn (): bool => Auth::user()?->canViewCosts() ?? false)
                    ->alignEnd()
                    ->state(fn (Equipment $record): string => 'Rp'.number_format((float) $record->book_value, 0, ',', '.')),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('room_id')
                    ->label('Ruangan')
                    ->relationship('room', 'name'),
                SelectFilter::make('equipment_type_id')
                    ->label('Jenis alat')
                    ->relationship('equipmentType', 'name'),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(EquipmentStatus::class),
                Filter::make('due_soon')
                    ->label('Jatuh tempo dalam 30 hari')
                    ->query(fn (Builder $query) => $query->dueBetween(today(), today()->addDays(30))),
                TernaryFilter::make('is_active')
                    ->label('Status aktif')
                    ->default(true)
                    ->indicateUsing(function (array $state): ?string {
                        $val = $state['value'] ?? null;

                        if ($val === '0' || $val === false || $val === 0) {
                            return 'Status aktif: Nonaktif';
                        }

                        if ($val === '' || $val === null) {
                            return 'Status aktif: Semua';
                        }

                        // Hide indicator chip when using the default active-only state.
                        return null;
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->label('Detail'),
                    EditAction::make()
                        ->label('Ubah'),
                    Action::make('print_qr')
                        ->label('Cetak label QR')
                        ->icon(Heroicon::OutlinedQrCode)
                        ->visible(fn (): bool => Auth::user()?->can('Update:Equipment') ?? false)
                        ->action(fn (Equipment $record) => app(QrLabelService::class)->downloadLabelsPdf(
                            collect([$record]),
                            "label-qr-{$record->inventory_code}.pdf",
                        )),
                    Action::make('deactivate')
                        ->label('Nonaktifkan')
                        ->icon(Heroicon::OutlinedNoSymbol)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Nonaktifkan Alat Kesehatan')
                        ->modalDescription('Alat yang dinonaktifkan tidak dihapus dari sistem agar riwayat kalibrasi dan perbaikan tetap tersimpan.')
                        ->visible(fn (Equipment $record): bool => $record->is_active && (Auth::user()?->can('Update:Equipment') ?? false))
                        ->action(fn (Equipment $record) => $record->update(['is_active' => false])),
                ])->tooltip('Aksi lainnya'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('print_qr_bulk')
                        ->label('Cetak label QR')
                        ->icon(Heroicon::OutlinedQrCode)
                        ->visible(fn (): bool => Auth::user()?->can('Update:Equipment') ?? false)
                        ->action(fn (Collection $records) => app(QrLabelService::class)->downloadLabelsPdf(
                            $records,
                            'label-qr-massal.pdf',
                        )),
                ]),
            ])
            ->emptyState(function (Table $table) {
                $livewire = $table->getLivewire();
                $hasSearch = filled($livewire->getTableSearch());
                $filters = $livewire->tableFilters ?? [];
                $hasActiveFilter = filled($filters['room_id']['value'] ?? null)
                    || filled($filters['equipment_type_id']['value'] ?? null)
                    || filled($filters['status']['value'] ?? null)
                    || (bool) ($filters['due_soon']['isActive'] ?? false);

                if ($hasSearch || $hasActiveFilter) {
                    return view('filament.tables.empty-state', [
                        'image' => 'empty-search',
                        'title' => 'Alat tidak ditemukan',
                        'description' => 'Coba ubah kata kunci pencarian atau atur ulang filter yang aktif.',
                    ]);
                }

                return view('filament.tables.empty-state', [
                    'image' => 'empty-equipment',
                    'title' => 'Belum ada alat kesehatan',
                    'description' => 'Tambahkan inventaris alat kesehatan untuk mulai memantau kalibrasi dan pemeliharaan.',
                ]);
            });
    }
}
