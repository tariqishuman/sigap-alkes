<?php

namespace App\Filament\Resources\EquipmentMovements\Schemas;

use App\Models\Equipment;
use App\Models\Room;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class EquipmentMovementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detail Mutasi Alat')
                    ->columns(2)
                    ->schema([
                        Select::make('equipment_id')
                            ->label('Alat')
                            ->relationship('equipment', 'name', fn (Builder $query) => $query->where('is_active', true))
                            ->getOptionLabelFromRecordUsing(fn (Equipment $record): string => "{$record->name} ({$record->inventory_code})")
                            ->searchable(['name', 'inventory_code'])
                            ->preload()
                            ->live()
                            ->default(fn (): ?int => request()->integer('equipment_id') ?: null)
                            ->required(),
                        Placeholder::make('current_room_name')
                            ->label('Ruangan Saat Ini (Otomatis)')
                            ->content(function (Get $get): string {
                                $equipmentId = $get('equipment_id');
                                if (! $equipmentId) {
                                    return 'Pilih alat terlebih dahulu';
                                }

                                return Equipment::with('room')->find($equipmentId)?->room?->name ?? '-';
                            }),
                        Select::make('to_room_id')
                            ->label('Ruangan Tujuan')
                            ->options(function (Get $get): array {
                                $currentRoomId = $get('equipment_id')
                                    ? Equipment::whereKey($get('equipment_id'))->value('room_id')
                                    : null;

                                return Room::query()
                                    ->when($currentRoomId, fn (Builder $q) => $q->where('id', '!=', $currentRoomId))
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all();
                            })
                            ->searchable()
                            ->required(),
                        DateTimePicker::make('moved_at')
                            ->label('Tanggal & Waktu Mutasi')
                            ->default(now())
                            ->maxDate(now())
                            ->seconds(false)
                            ->required(),
                        Toggle::make('is_reinstallation')
                            ->label('Termasuk reinstalasi alat')
                            ->helperText('Jika aktif (atau jenis alat memerlukan instalasi) dan status alat Laik pakai, status berubah menjadi Perlu kalibrasi.')
                            ->default(false)
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->label('Catatan Mutasi')
                            ->rows(3)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
