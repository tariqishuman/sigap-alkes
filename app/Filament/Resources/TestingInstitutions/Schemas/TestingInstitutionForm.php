<?php

namespace App\Filament\Resources\TestingInstitutions\Schemas;

use App\Enums\TestingInstitutionKind;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TestingInstitutionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Data Lembaga Penguji')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Lembaga')
                            ->placeholder('Contoh: BPAFK Surabaya')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Select::make('kind')
                            ->label('Jenis Lembaga')
                            ->options(TestingInstitutionKind::class)
                            ->required(),
                        TextInput::make('accreditation_number')
                            ->label('Nomor Akreditasi')
                            ->placeholder('Contoh: LK-042-IDN')
                            ->maxLength(255),
                    ]),
            ]);
    }
}
