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

class CalibrationsTable
{
    // Also used by the equipment relation manager, so edit is a plain link rather than a modal.
    public static function configure(Table $table, bool $forEquipmentRelation = false): Table
    {
        return $table
            ->defaultSort('tested_at', 'desc')
            ->columns(array_values(array_filter([
                TextColumn::make('tested_at')
                    ->label('Tanggal uji')
                    ->date('d M Y')
                    ->sortable(),
                $forEquipmentRelation ? null : TextColumn::make('equipment.name')
                    ->label('Alat')
                    ->weight('medium')
                    ->searchable(['name', 'inventory_code'])
                    ->description(fn (Calibration $record): string => $record->equipment?->inventory_code ?? '-'),
                TextColumn::make('result')
                    ->label('Hasil')
                    ->badge(),
                TextColumn::make('testingInstitution.name')
                    ->label('Lembaga penguji')
                    ->placeholder('-'),
                TextColumn::make('certificate_number')
                    ->label('Nomor sertifikat')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('valid_until')
                    ->label('Berlaku s.d.')
                    ->date('d M Y')
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('certificate_path')
                    ->label('File sertifikat')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? 'PDF tersedia' : 'Belum diunggah')
                    ->badge()
                    ->color(fn (?string $state): string => filled($state) ? 'success' : 'gray')
                    ->placeholder('Belum diunggah'),
                TextColumn::make('recordedByUser.name')
                    ->label('Dicatat oleh')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])))
            ->filters([
                SelectFilter::make('result')
                    ->label('Hasil')
                    ->options(CalibrationResult::class),
                SelectFilter::make('testing_institution_id')
                    ->label('Lembaga Penguji')
                    ->relationship('testingInstitution', 'name'),
            ])
            ->recordActions([
                Action::make('view_certificate')
                    ->label('Lihat sertifikat')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->visible(fn (Calibration $record): bool => filled($record->certificate_path))
                    ->url(fn (Calibration $record): string => route('calibrations.certificate', $record))
                    ->openUrlInNewTab(),
                Action::make('edit')
                    ->label('Ubah')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->visible(fn (Calibration $record): bool => CalibrationResource::canEdit($record))
                    ->url(fn (Calibration $record): string => CalibrationResource::getUrl('edit', ['record' => $record])),
            ])
            ->emptyState(view('filament.tables.empty-state', [
                'image' => 'empty-calibration',
                'title' => 'Belum ada riwayat kalibrasi',
                'description' => 'Catat hasil uji dan kalibrasi untuk mulai memantau masa berlaku sertifikat alat.',
            ]));
    }
}
