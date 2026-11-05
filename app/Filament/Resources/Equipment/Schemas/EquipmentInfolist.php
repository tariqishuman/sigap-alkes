<?php

namespace App\Filament\Resources\Equipment\Schemas;

use App\Models\Equipment;
use App\Services\DepreciationCalculator;
use App\Services\QrLabelService;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class EquipmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Alat & Status')
                    ->columns(3)
                    ->schema([
                        ImageEntry::make('photo_path')
                            ->label('Foto Alat')
                            ->disk('public')
                            ->defaultImageUrl(url('/images/equipment-placeholder.svg'))
                            ->imageHeight(88)
                            ->square(),
                        TextEntry::make('inventory_code')
                            ->label('Kode Inventaris')
                            ->fontFamily('mono')
                            ->weight('bold'),
                        TextEntry::make('name')
                            ->label('Nama Alat')
                            ->weight('bold'),
                        TextEntry::make('status')
                            ->label('Status Saat Ini')
                            ->badge(),
                        TextEntry::make('room.name')
                            ->label('Ruangan'),
                        TextEntry::make('equipmentType.name')
                            ->label('Jenis Alat'),
                        IconEntry::make('is_active')
                            ->label('Status Aktif')
                            ->boolean(),
                        TextEntry::make('brand')
                            ->label('Merek')
                            ->placeholder('-'),
                        TextEntry::make('model')
                            ->label('Tipe / Model')
                            ->placeholder('-'),
                        TextEntry::make('serial_number')
                            ->label('Nomor Seri')
                            ->placeholder('-'),
                        TextEntry::make('qr_preview')
                            ->label('QR Status Publik')
                            ->state(function (Equipment $record): HtmlString {
                                $dataUri = app(QrLabelService::class)->generateQrDataUri($record);
                                $publicUrl = route('public.equipment.show', ['qr_token' => $record->qr_token]);

                                return new HtmlString(
                                    '<div class="flex items-center gap-3">'
                                    .'<img src="'.$dataUri.'" alt="QR" class="h-16 w-16 border rounded p-1 bg-white" />'
                                    .'<a href="'.$publicUrl.'" target="_blank" class="text-sm underline text-primary-600">Buka halaman status publik</a>'
                                    .'</div>'
                                );
                            }),
                        TextEntry::make('notes')
                            ->label('Catatan')
                            ->placeholder('-')
                            ->columnSpan(2),
                    ]),

                Section::make('Kalibrasi')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('latestValidCalibration.certificate_number')
                            ->label('Sertifikat Terakhir')
                            ->placeholder('Belum ada sertifikat'),
                        TextEntry::make('latestValidCalibration.valid_until')
                            ->label('Berlaku s.d.')
                            ->date('d M Y')
                            ->placeholder('-'),
                        TextEntry::make('days_until_due')
                            ->label('Sisa Hari')
                            ->state(function (Equipment $record): string {
                                $validUntil = $record->latestValidCalibration?->valid_until;

                                if (! $validUntil) {
                                    return '-';
                                }

                                $daysLeft = (int) today()->diffInDays($validUntil, false);

                                return $daysLeft >= 0 ? "{$daysLeft} hari" : 'Lewat '.abs($daysLeft).' hari';
                            }),
                    ]),

                Section::make('Nilai Aset')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('acquisition_date')
                            ->label('Tanggal Perolehan')
                            ->date('d M Y'),
                        TextEntry::make('acquisition_cost')
                            ->label('Harga Perolehan')
                            ->formatStateUsing(fn ($state): string => 'Rp'.number_format((float) $state, 0, ',', '.')),
                        TextEntry::make('annual_depreciation_display')
                            ->label('Penyusutan / Tahun')
                            ->state(function (Equipment $record): string {
                                $calc = app(DepreciationCalculator::class)->calculateForEquipment($record);

                                return 'Rp'.number_format((float) $calc['annual_depreciation'], 0, ',', '.');
                            }),
                        TextEntry::make('accumulated_depreciation_display')
                            ->label('Akumulasi Penyusutan')
                            ->state(function (Equipment $record): string {
                                $calc = app(DepreciationCalculator::class)->calculateForEquipment($record);

                                return 'Rp'.number_format((float) $calc['accumulated_depreciation'], 0, ',', '.');
                            }),
                        TextEntry::make('book_value_display')
                            ->label('Nilai Buku Saat Ini')
                            ->weight('bold')
                            ->state(function (Equipment $record): string {
                                $calc = app(DepreciationCalculator::class)->calculateForEquipment($record);

                                return 'Rp'.number_format((float) $calc['book_value'], 0, ',', '.');
                            }),
                    ]),
            ]);
    }
}
