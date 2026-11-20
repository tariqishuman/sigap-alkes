<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Services\DashboardMetricsService;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\Auth;

class DamagesBreakdownChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 2;

    protected static bool $isLazy = true;

    protected ?string $heading = 'Kerusakan per Jenis Alat';

    protected ?string $maxHeight = '240px';

    public ?string $filter = 'type';

    protected string $view = 'filament.widgets.damages-breakdown-chart';

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'xl' => 9,
    ];

    public static function canView(): bool
    {
        return Auth::user()?->hasAnyRole([
            UserRole::SuperAdmin->value,
            UserRole::AdminIpsrs->value,
            UserRole::Manajemen->value,
        ]) ?? false;
    }

    public function setGroupBy(string $groupBy): void
    {
        $this->filter = $groupBy === 'room' ? 'room' : 'type';
        $this->updateChartData();
    }

    /**
     * @return array<string, mixed>
     */
    public function getBreakdown(): array
    {
        $period = (string) ($this->pageFilters['period'] ?? 'monthly');
        $date = $this->pageFilters['date'] ?? null;
        $roomId = filled($this->pageFilters['room_id'] ?? null) ? (int) $this->pageFilters['room_id'] : null;

        return app(DashboardMetricsService::class)->damagesBreakdown(
            $period,
            is_string($date) ? $date : null,
            $roomId,
            $this->filter ?? 'type',
        );
    }

    protected function getData(): array
    {
        $breakdown = $this->getBreakdown();
        $items = $breakdown['items'];

        return [
            'datasets' => [
                [
                    'label' => 'Selesai',
                    'data' => array_map(fn (array $item): int => $item['resolved'], $items),
                    'backgroundColor' => '#0E7C7B',
                    'borderRadius' => 6,
                    'maxBarThickness' => 44,
                    'categoryPercentage' => 0.6,
                    'stack' => 'damages',
                ],
                [
                    'label' => 'Masih terbuka',
                    'data' => array_map(fn (array $item): int => $item['open'], $items),
                    'backgroundColor' => '#5CC8C4',
                    'borderRadius' => 6,
                    'maxBarThickness' => 44,
                    'categoryPercentage' => 0.6,
                    'stack' => 'damages',
                ],
            ],
            'labels' => array_map(fn (array $item): string => $item['label'], $items),
        ];
    }

    protected function getOptions(): array
    {
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
                'x' => [
                    'stacked' => true,
                    'grid' => ['display' => false],
                    'border' => ['color' => '#D3DEDD'],
                    'ticks' => [
                        'color' => '#97A6A9',
                        'font' => ['size' => 11],
                    ],
                ],
                'y' => [
                    'stacked' => true,
                    'beginAtZero' => true,
                    'border' => ['display' => false],
                    'grid' => [
                        'color' => '#E3EAEA',
                        'tickLength' => 0,
                    ],
                    'ticks' => [
                        'color' => '#97A6A9',
                        'font' => ['size' => 11],
                        'precision' => 0,
                        'maxTicksLimit' => 5,
                    ],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
