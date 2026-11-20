<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Services\UptimeCalculator;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class MonthlyUptimeChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Uptime 12 Bulan Terakhir (%)';

    protected ?string $description = 'Rata-rata seluruh alat aktif';

    protected ?string $maxHeight = '280px';

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'xl' => 6,
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
                    'backgroundColor' => 'rgba(14, 124, 123, 0.10)',
                    'borderWidth' => 2.5,
                    'pointRadius' => 3.5,
                    'pointHoverRadius' => 5.5,
                    'pointBackgroundColor' => '#FFFFFF',
                    'pointBorderColor' => '#0E7C7B',
                    'pointBorderWidth' => 2,
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => array_map(fn (array $point): string => $point['label'], $series),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'y' => [
                    'suggestedMin' => 85,
                    'suggestedMax' => 100,
                    'ticks' => [
                        'stepSize' => 5,
                    ],
                    'grid' => [
                        'color' => 'rgba(221, 231, 230, 0.7)',
                    ],
                ],
                'x' => [
                    'grid' => [
                        'display' => false,
                    ],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
