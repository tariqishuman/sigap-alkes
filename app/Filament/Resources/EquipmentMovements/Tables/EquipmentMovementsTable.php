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
                TextColumn::make('equipment.inventory_code')
                    ->label('Kode Inventaris')
                    ->fontFamily('mono')
                    ->searchable(),
                TextColumn::make('equipment.name')
                    ->label('Alat')
                    ->searchable(),
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
            ->recordActions([]);
    }
}
