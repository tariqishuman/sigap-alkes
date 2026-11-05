<?php

namespace App\Filament\Resources\Calibrations\Tables;

use App\Enums\CalibrationResult;
use App\Filament\Resources\Calibrations\CalibrationResource;
use App\Models\Calibration;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class CalibrationsTable
{
    // Also used by the equipment relation manager, so edit is a plain link rather than a modal.
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('tested_at', 'desc')
            ->columns([
                TextColumn::make('tested_at')
                    ->label('Tanggal Uji')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('equipment.inventory_code')
                    ->label('Kode Inventaris')
                    ->fontFamily('mono')
                    ->searchable(),
                TextColumn::make('equipment.name')
                    ->label('Alat')
                    ->searchable(),
                TextColumn::make('result')
                    ->label('Hasil')
                    ->badge(),
                TextColumn::make('testingInstitution.name')
                    ->label('Lembaga Penguji')
                    ->placeholder('-'),
                TextColumn::make('certificate_number')
                    ->label('Nomor Sertifikat')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('valid_until')
                    ->label('Berlaku s.d.')
                    ->date('d M Y')
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('recordedByUser.name')
                    ->label('Dicatat Oleh')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('result')
                    ->label('Hasil')
                    ->options(CalibrationResult::class),
                SelectFilter::make('testing_institution_id')
                    ->label('Lembaga Penguji')
                    ->relationship('testingInstitution', 'name'),
            ])
            ->recordActions([
                Action::make('download_certificate')
                    ->label('Unduh sertifikat')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->visible(fn (Calibration $record): bool => filled($record->certificate_path))
                    ->action(fn (Calibration $record) => Storage::disk('local')->download($record->certificate_path)),
                Action::make('edit')
                    ->label('Ubah')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->visible(fn (Calibration $record): bool => CalibrationResource::canEdit($record))
                    ->url(fn (Calibration $record): string => CalibrationResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
