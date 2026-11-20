<?php

namespace App\Filament\Resources\Rooms\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RoomForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Ruangan')
                    ->columns(3)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Ruangan')
                            ->placeholder('Contoh: Ruang ICU')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('building')
                            ->label('Gedung')
                            ->placeholder('Contoh: Gedung Utama')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('floor')
                            ->label('Lantai')
                            ->placeholder('Contoh: Lantai 2')
                            ->required()
                            ->maxLength(100),
                        Toggle::make('is_critical_area')
                            ->label('Area Kritis (ICU / IGD / OK / NICU)')
                            ->helperText('Seluruh alat di area kritis akan mendapat prioritas notifikasi push Kritis.')
                            ->default(false)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
