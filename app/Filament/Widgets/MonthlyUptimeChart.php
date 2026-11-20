<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Services\UptimeCalculator;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class MonthlyUptimeChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Uptime Rata-Rata 12 Bulan Terakhir (%)';

    protected ?string $description = 'Dihitung dari (ΣT − ΣD) / ΣT × 100% seluruh alat aktif';

    protected int|string|array $columnSpan = [
        'md' => 2,
        'xl' => 2,
    ];

    public static function canView(): bool
    {
        return Auth::user()?->hasAnyRole([
            UserRole::SuperAdmin->value,
            UserRole::AdminIpsrs->value,
            UserRole::Manajemen->value,
        ]) ?? false;
    }

    protected function getData(): array
    {
        $series = app(UptimeCalculator::class)->monthlySeries(12);

        return [
            'datasets' => [
                [
                    'label' => 'Uptime (%)',
                    'data' => array_map(fn (array $point): ?float => $point['uptime_percent'], $series),
                    'borderColor' => '#0E7C7B',
                    'backgroundColor' => 'rgba(14, 124, 123, 0.12)',
                    'fill' => true,
                    'tension' => 0.25,
                ],
            ],
            'labels' => array_map(fn (array $point): string => $point['label'], $series),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
