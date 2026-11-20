<?php

namespace App\Filament\Resources\SpareParts\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SparePartForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Data Suku Cadang')
                    ->columns(3)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Suku Cadang')
                            ->placeholder('Contoh: Baterai Li-Ion 12V')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('unit')
                            ->label('Satuan')
                            ->placeholder('Contoh: pcs / unit / set')
                            ->default('pcs')
                            ->required()
                            ->maxLength(50),
                        TextInput::make('min_stock')
                            ->label('Stok Minimum')
                            ->integer()
                            ->minValue(0)
                            ->default(0)
                            ->helperText('Batas peringatan stok menipis.')
                            ->required(),
                    ]),
            ]);
    }
}
