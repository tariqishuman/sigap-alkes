<?php

namespace App\Filament\Resources\Equipment\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EquipmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identitas & Lokasi Alat')
                    ->columns(2)
                    ->schema([
                        TextInput::make('inventory_code')
                            ->label('Kode Inventaris')
                            ->placeholder('Contoh: ICU-001')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(100),
                        TextInput::make('name')
                            ->label('Nama Alat')
                            ->placeholder('Contoh: Infusion Pump Terumo')
                            ->required()
                            ->maxLength(255),
                        Select::make('equipment_type_id')
                            ->label('Jenis Alat')
                            ->relationship('equipmentType', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('room_id')
                            ->label('Ruangan')
                            ->relationship('room', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('brand')
                            ->label('Merek')
                            ->maxLength(255),
                        TextInput::make('model')
                            ->label('Tipe / Model')
                            ->maxLength(255),
                        TextInput::make('serial_number')
                            ->label('Nomor Seri')
                            ->maxLength(255),
                        Toggle::make('is_active')
                            ->label('Alat Aktif Digunakan')
                            ->default(true)
                            ->required(),
                    ]),

                Section::make('Perolehan & Nilai Aset')
                    ->columns(3)
                    ->schema([
                        DatePicker::make('acquisition_date')
                            ->label('Tanggal Perolehan')
                            ->maxDate(now())
                            ->required(),
                        TextInput::make('acquisition_cost')
                            ->label('Harga Perolehan')
                            ->numeric()
                            ->prefix('Rp')
                            ->minValue(0)
                            ->required(),
                        TextInput::make('residual_value')
                            ->label('Nilai Residu (Opsional)')
                            ->numeric()
                            ->prefix('Rp')
                            ->minValue(0)
                            ->helperText('Kosongkan untuk memakai % residu dari jenis alat.'),
                    ]),

                Section::make('Foto & Catatan')
                    ->columns(2)
                    ->schema([
                        FileUpload::make('photo_path')
                            ->label('Foto Alat')
                            ->image()
                            ->disk('public')
                            ->directory('equipment-photos')
                            ->visibility('public')
                            ->maxSize(2048),
                        Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(4),
                    ]),
            ]);
    }
}
