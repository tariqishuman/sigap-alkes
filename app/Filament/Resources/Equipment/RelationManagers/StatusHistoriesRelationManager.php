<?php

namespace App\Filament\Resources\Equipment\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StatusHistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'statusHistories';

    protected static ?string $title = 'Riwayat Status';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reason')
            ->defaultSort('changed_at', 'desc')
            ->columns([
                TextColumn::make('changed_at')
                    ->label('Waktu Perubahan')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('from_status')
                    ->label('Dari Status')
                    ->badge()
                    ->placeholder('Awal'),
                TextColumn::make('to_status')
                    ->label('Ke Status')
                    ->badge(),
                TextColumn::make('reason')
                    ->label('Alasan / Pemicu')
                    ->wrap(),
                TextColumn::make('changedByUser.name')
                    ->label('Diubah Oleh')
                    ->placeholder('Sistem'),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
