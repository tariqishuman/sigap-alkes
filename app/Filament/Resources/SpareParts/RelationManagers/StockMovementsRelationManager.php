<?php

namespace App\Filament\Resources\SpareParts\RelationManagers;

use App\Models\SparePartMovement;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StockMovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'movements';

    protected static ?string $title = 'Riwayat Stok';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('moved_at', 'desc')
            ->columns([
                TextColumn::make('moved_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Jenis')
                    ->badge(),
                TextColumn::make('quantity')
                    ->label('Perubahan')
                    ->formatStateUsing(fn (SparePartMovement $record): string => $record->quantity > 0 ? "+{$record->quantity}" : (string) $record->quantity)
                    ->weight('bold'),
                TextColumn::make('user.name')
                    ->label('Oleh')
                    ->placeholder('-'),
                TextColumn::make('notes')
                    ->label('Keterangan')
                    ->placeholder('-')
                    ->wrap(),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
