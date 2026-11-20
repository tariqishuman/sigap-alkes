<?php

namespace App\Filament\Pages;

use App\Services\SettingsService;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getSubheading(): ?string
    {
        $hospital = app(SettingsService::class)->hospitalName();
        $today = now()->translatedFormat('l, d F Y');

        return "{$hospital} • {$today}";
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
