<?php

namespace App\Filament\Resources\Equipment\Tables;

use App\Enums\EquipmentStatus;
use App\Models\Equipment;
use App\Services\QrLabelService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class EquipmentTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo_path')
                    ->label('Foto')
                    ->disk('public')
                    ->defaultImageUrl(url('/images/equipment-placeholder.svg'))
                    ->square()
                    ->imageSize(44),
                TextColumn::make('inventory_code')
                    ->label('Kode Inventaris')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono'),
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('room.name')
                    ->label('Ruangan')
                    ->sortable(),
                TextColumn::make('equipmentType.name')
                    ->label('Jenis')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('valid_until_display')
                    ->label('Berlaku s.d.')
                    ->state(function (Equipment $record): string {
                        if (method_exists($record, 'latestValidCalibration') && $record->latestValidCalibration?->valid_until) {
                            return $record->latestValidCalibration->valid_until->translatedFormat('d M Y');
                        }

                        return '-';
                    }),
                TextColumn::make('book_value')
                    ->label('Nilai Buku')
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
                    ->label('Jenis Alat')
                    ->relationship('equipmentType', 'name'),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(EquipmentStatus::class),
                TernaryFilter::make('is_active')
                    ->label('Status Aktif')
                    ->default(true),
            ])
            ->recordActions([
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
            ]);
    }
}
