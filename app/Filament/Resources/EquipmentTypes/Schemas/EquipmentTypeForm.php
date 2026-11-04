<?php

namespace App\Filament\Resources\EquipmentTypes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EquipmentTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Jenis Alat')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Jenis Alat')
                            ->placeholder('Contoh: Infusion Pump')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Toggle::make('requires_calibration')
                            ->label('Wajib Kalibrasi Berkala')
                            ->default(true)
                            ->required(),
                        Toggle::make('requires_installation')
                            ->label('Wajib Kalibrasi Ulang Saat Pindah Ruangan / Instalasi')
                            ->default(false)
                            ->required(),
                        TextInput::make('calibration_interval_months')
                            ->label('Interval Kalibrasi (Bulan)')
                            ->numeric()
                            ->default(12)
                            ->minValue(1)
                            ->maxValue(120)
                            ->suffix('bulan')
                            ->required(),
                        TextInput::make('useful_life_years')
                            ->label('Masa Manfaat Penyusutan (Tahun)')
                            ->numeric()
                            ->default(5)
                            ->minValue(1)
                            ->maxValue(50)
                            ->suffix('tahun')
                            ->required(),
                        TextInput::make('residual_value_percent')
                            ->label('Persentase Nilai Residu Default')
                            ->numeric()
                            ->default(10)
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->required(),
                    ]),
            ]);
    }
}
