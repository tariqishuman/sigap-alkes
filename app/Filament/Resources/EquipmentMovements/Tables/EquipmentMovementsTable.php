<?php

namespace App\Filament\Resources\EquipmentMovements\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EquipmentMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('moved_at', 'desc')
            ->columns([
                TextColumn::make('moved_at')
                    ->label('Tanggal Mutasi')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('equipment.name')
                    ->label('Alat')
                    ->weight('medium')
                    ->searchable(['name', 'inventory_code'])
                    ->description(fn ($record): string => $record->equipment?->inventory_code ?? '-'),
                TextColumn::make('fromRoom.name')
                    ->label('Dari Ruangan'),
                TextColumn::make('toRoom.name')
                    ->label('Ke Ruangan'),
                IconColumn::make('is_reinstallation')
                    ->label('Reinstalasi')
                    ->boolean(),
                TextColumn::make('movedByUser.name')
                    ->label('Dipindahkan Oleh')
                    ->placeholder('-'),
                TextColumn::make('notes')
                    ->label('Catatan')
                    ->placeholder('-')
                    ->limit(50)
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('from_room_id')
                    ->label('Dari Ruangan')
                    ->relationship('fromRoom', 'name'),
                SelectFilter::make('to_room_id')
                    ->label('Ke Ruangan')
                    ->relationship('toRoom', 'name'),
            ])
            ->recordActions([])
            ->emptyState(view('filament.tables.empty-state', [
                'image' => 'empty-movement',
                'title' => 'Belum ada mutasi alat',
                'description' => 'Riwayat perpindahan ruangan alat kesehatan akan tampil di sini.',
            ]));
    }
}
