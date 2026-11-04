<?php

namespace App\Filament\Resources\EquipmentTypes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EquipmentTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Jenis Alat')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('requires_calibration')
                    ->label('Wajib Kalibrasi')
                    ->boolean(),
                IconColumn::make('requires_installation')
                    ->label('Wajib Uji Mutasi')
                    ->boolean(),
                TextColumn::make('calibration_interval_months')
                    ->label('Interval')
                    ->suffix(' bln')
                    ->sortable(),
                TextColumn::make('useful_life_years')
                    ->label('Masa Manfaat')
                    ->suffix(' thn')
                    ->sortable(),
                TextColumn::make('residual_value_percent')
                    ->label('Nilai Residu')
                    ->suffix('%')
                    ->sortable(),
                TextColumn::make('equipment_count')
                    ->label('Jumlah Alat')
                    ->counts('equipment')
                    ->badge()
                    ->color('primary'),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make()->label('Ubah'),
                DeleteAction::make()->label('Hapus'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Hapus terpilih'),
                ]),
            ]);
    }
}
