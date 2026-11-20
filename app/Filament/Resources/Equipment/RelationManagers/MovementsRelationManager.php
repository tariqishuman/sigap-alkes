<?php

namespace App\Filament\Resources\Equipment\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'movements';

    protected static ?string $title = 'Mutasi';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('moved_at')
            ->defaultSort('moved_at', 'desc')
            ->columns([
                TextColumn::make('moved_at')
                    ->label('Tanggal Mutasi')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
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
                    ->wrap(),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([])
            ->emptyState(view('filament.tables.empty-state', [
                'image' => 'empty-movement',
                'title' => 'Belum ada mutasi alat',
                'description' => 'Alat ini belum pernah dipindahkan ke ruangan lain.',
            ]));
    }
}
