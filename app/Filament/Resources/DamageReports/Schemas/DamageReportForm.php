<?php

namespace App\Filament\Resources\DamageReports\Schemas;

use App\Enums\UserRole;
use App\Models\Equipment;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class DamageReportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detail Laporan Kerusakan')
                    ->columns(2)
                    ->schema([
                        Select::make('equipment_id')
                            ->label('Alat')
                            ->relationship('equipment', 'name', function (Builder $query, string $operation) {
                                if ($operation === 'create') {
                                    $query->where('is_active', true);

                                    $user = Auth::user();
                                    if (
                                        $user
                                        && $user->hasRole(UserRole::KepalaRuangan->value)
                                        && ! $user->hasAnyRole([
                                            UserRole::SuperAdmin->value,
                                            UserRole::AdminIpsrs->value,
                                            UserRole::Teknisi->value,
                                        ])
                                    ) {
                                        $query->where('room_id', $user->room_id ?? 0);
                                    }
                                }
                            })
                            ->getOptionLabelFromRecordUsing(fn (Equipment $record): string => "{$record->name} ({$record->inventory_code})")
                            ->searchable(['name', 'inventory_code'])
                            ->preload()
                            ->default(fn (): ?int => request()->integer('equipment_id') ?: null)
                            ->disabledOn('edit')
                            ->required(),
                        DateTimePicker::make('reported_at')
                            ->label('Waktu Lapor')
                            ->default(now())
                            ->maxDate(now())
                            ->seconds(false)
                            ->disabledOn('edit')
                            ->required(),
                        Textarea::make('complaint')
                            ->label('Keluhan / Deskripsi Kerusakan')
                            ->placeholder('Jelaskan gejala kerusakan yang ditemukan pada alat...')
                            ->rows(4)
                            ->maxLength(2000)
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
