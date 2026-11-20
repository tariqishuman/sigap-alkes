<?php

namespace App\Filament\Resources\Repairs\Schemas;

use App\Models\Repair;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RepairInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detail Perbaikan')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('damageReport.equipment.inventory_code')
                            ->label('Kode Inventaris')
                            ->fontFamily('mono')
                            ->weight('bold'),
                        TextEntry::make('damageReport.equipment.name')
                            ->label('Nama Alat')
                            ->weight('bold'),
                        TextEntry::make('damageReport.status')
                            ->label('Status Laporan')
                            ->badge(),
                        TextEntry::make('technician.name')
                            ->label('Teknisi')
                            ->placeholder('-'),
                        TextEntry::make('started_at')
                            ->label('Waktu Mulai')
                            ->dateTime('d M Y H:i'),
                        TextEntry::make('finished_at')
                            ->label('Waktu Selesai')
                            ->dateTime('d M Y H:i')
                            ->placeholder('Masih berlangsung'),
                        TextEntry::make('damageReport.complaint')
                            ->label('Keluhan Awal')
                            ->columnSpanFull(),
                        TextEntry::make('action')
                            ->label('Tindakan Perbaikan')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),

                Section::make('Rincian Biaya')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('labor_cost')
                            ->label('Biaya Jasa')
                            ->formatStateUsing(fn ($state): string => 'Rp'.number_format((float) $state, 0, ',', '.')),
                        TextEntry::make('spare_parts_cost')
                            ->label('Total Suku Cadang')
                            ->state(fn (Repair $record): string => 'Rp'.number_format((float) $record->sparePartsCost(), 0, ',', '.')),
                        TextEntry::make('total_cost')
                            ->label('Total Biaya Perbaikan')
                            ->weight('bold')
                            ->state(fn (Repair $record): string => 'Rp'.number_format((float) $record->totalCost(), 0, ',', '.')),
                    ]),

                Section::make('Suku Cadang yang Digunakan')
                    ->schema([
                        RepeatableEntry::make('repairSpareParts')
                            ->label('')
                            ->placeholder('Tidak ada suku cadang yang digunakan.')
                            ->columns(4)
                            ->schema([
                                TextEntry::make('sparePart.name')
                                    ->label('Suku Cadang'),
                                TextEntry::make('quantity')
                                    ->label('Jumlah'),
                                TextEntry::make('unit_price')
                                    ->label('Harga Satuan')
                                    ->formatStateUsing(fn ($state): string => 'Rp'.number_format((float) $state, 0, ',', '.')),
                                TextEntry::make('subtotal')
                                    ->label('Subtotal')
                                    ->state(fn ($record): string => 'Rp'.number_format((float) $record->lineTotal(), 0, ',', '.')),
                            ]),
                    ]),
            ]);
    }
}
