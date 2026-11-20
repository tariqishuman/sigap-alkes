<?php

namespace App\Filament\Resources\Equipment\Schemas;

use App\Models\Equipment;
use App\Services\DepreciationCalculator;
use App\Services\SettingsService;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class EquipmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                ViewEntry::make('detail_header')
                    ->hiddenLabel()
                    ->view('filament.equipment.detail-header')
                    ->columnSpanFull(),

                Section::make('Nilai Aset')
                    ->description('Nilai buku adalah estimasi, bukan pembukuan resmi RS.')
                    ->visible(fn (): bool => Auth::user()?->canViewCosts() ?? false)
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('acquisition_date')
                            ->label('Tanggal Perolehan')
                            ->date('d M Y'),
                        TextEntry::make('acquisition_cost')
                            ->label('Harga Perolehan')
                            ->formatStateUsing(fn ($state): string => 'Rp'.number_format((float) $state, 0, ',', '.')),
                        TextEntry::make('monthly_depreciation_display')
                            ->label('Penyusutan per Bulan')
                            ->state(function (Equipment $record): string {
                                $calc = app(DepreciationCalculator::class)->calculateForEquipment($record);

                                return 'Rp'.number_format((float) $calc['monthly_depreciation'], 0, ',', '.');
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
                        TextEntry::make('total_repair_cost_display')
                            ->label('Total Biaya Perbaikan')
                            ->weight('bold')
                            ->state(fn (Equipment $record): string => 'Rp'.number_format((float) $record->totalRepairCost(), 0, ',', '.')),
                        ViewEntry::make('replacement_banner')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->visible(fn (Equipment $record): bool => app(DepreciationCalculator::class)->shouldConsiderReplacement($record))
                            ->view('filament.equipment.replacement-banner', [
                                'threshold' => app(SettingsService::class)->replaceThresholdPercent(),
                            ]),
                    ]),
            ]);
    }
}
