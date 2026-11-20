<?php

namespace App\Filament\Pages;

use App\Services\SettingsService;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class Dashboard extends BaseDashboard
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Squares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Umum';

    protected static ?string $navigationLabel = 'Ringkasan';

    protected static ?string $title = 'Ringkasan';

    protected static ?int $navigationSort = 1;

    public function getSubheading(): ?string
    {
        $hospital = app(SettingsService::class)->hospitalName();
        $today = now()->translatedFormat('d F Y');

        return "Kondisi alat kesehatan {$hospital} per {$today}";
    }

    public function getColumns(): int|array
    {
        return [
            'default' => 1,
            'md' => 12,
            'xl' => 12,
        ];
    }
}
