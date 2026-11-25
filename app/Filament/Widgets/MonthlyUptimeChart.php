<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Services\DashboardMetricsService;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class MonthlyUptimeChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 4;

    protected static bool $isLazy = true;

    public function placeholder(): View
    {
        return view('filament.widgets.skeleton-card', [
            'variant' => 'line',
            'title' => 'Memuat grafik uptime 12 bulan',
        ]);
    }

    protected ?string $heading = 'Uptime 12 Bulan Terakhir';

    protected ?string $maxHeight = '220px';

    protected string $view = 'filament.widgets.monthly-uptime-chart';

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'xl' => 8,
    ];

    public static function canView(): bool
    {
        return Auth::user()?->hasAnyRole([
            UserRole::SuperAdmin->value,
            UserRole::AdminIpsrs->value,
            UserRole::Manajemen->value,
            UserRole::Demo->value,
        ]) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTrend(): array
    {
        $roomId = filled($this->pageFilters['room_id'] ?? null) ? (int) $this->pageFilters['room_id'] : null;

        return app(DashboardMetricsService::class)->uptimeTrend(12, $roomId);
    }

    protected function getData(): array
    {
        $trend = $this->getTrend();
        $values = $trend['values'];
        $count = count($values);
        $target = (float) $trend['target_percent'];

        // Show point marker ONLY on the last month per design.md §3.8
        $pointRadii = [];
        for ($i = 0; $i < $count; $i++) {
            $pointRadii[] = ($i === $count - 1) ? 4.5 : 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Uptime (%)',
                    'data' => $values,
                    'borderColor' => '#0E7C7B',
                    'backgroundColor' => 'rgba(14, 124, 123, 0.12)',
                    'borderWidth' => 2.5,
                    'pointRadius' => $pointRadii,
                    'pointHoverRadius' => 5.5,
                    'pointBackgroundColor' => '#FFFFFF',
                    'pointBorderColor' => '#0E7C7B',
                    'pointBorderWidth' => 2,
                    'fill' => true,
                    'tension' => 0,
                ],
                [
                    'label' => "Target {$trend['target_percent']}%",
                    'data' => array_fill(0, $count, $target),
                    'borderColor' => '#97A6A9',
                    'borderWidth' => 1.25,
                    'borderDash' => [6, 5],
                    'pointRadius' => 0,
                    'pointHoverRadius' => 0,
                    'fill' => false,
                    'tension' => 0,
                ],
            ],
            'labels' => $trend['labels'],
        ];
    }

    protected function getOptions(): array
    {
        $trend = $this->getTrend();

        return [
            'animation' => ['duration' => 0],
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['display' => false],
                'tooltip' => [
                    'backgroundColor' => '#13262B',
                    'titleColor' => '#FFFFFF',
                    'bodyColor' => '#FFFFFF',
                    'cornerRadius' => 8,
                    'padding' => 10,
                ],
            ],
            'scales' => [
                'y' => [
                    'min' => $trend['y_min'],
                    'max' => 100,
                    'border' => ['display' => false],
                    'grid' => [
                        'color' => '#E3EAEA',
                        'tickLength' => 0,
                    ],
                    'ticks' => [
                        'color' => '#97A6A9',
                        'font' => ['size' => 11],
                        'maxTicksLimit' => 5,
                    ],
                ],
                'x' => [
                    'grid' => ['display' => false],
                    'border' => ['color' => '#D3DEDD'],
                    'ticks' => [
                        'color' => '#97A6A9',
                        'font' => ['size' => 11],
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
