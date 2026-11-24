<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Services\DashboardMetricsService;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class DamagesBreakdownChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 2;

    protected static bool $isLazy = true;

    public function placeholder(): View
    {
        return view('filament.widgets.skeleton-card', [
            'variant' => 'chart',
            'title' => 'Memuat grafik kerusakan',
        ]);
    }

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
            'labels' => array_map(fn (array $item): string|array => $this->wrapLabel($item['label']), $items),
        ];
    }

    /**
     * Wrap long category labels onto at most 2 short horizontal lines (max 16 chars each)
     * so the X-axis stays completely horizontal while Chart.js tooltips still display the full name.
     *
     * @return string|array<int, string>
     */
    private function wrapLabel(string $label, int $maxLine = 16): string|array
    {
        if (mb_strlen($label) <= $maxLine) {
            return $label;
        }

        $words = preg_split('/\s+/', trim($label)) ?: [$label];
        $line1 = '';
        $line2 = '';

        foreach ($words as $word) {
            if ($line1 === '' || mb_strlen($line1.' '.$word) <= $maxLine) {
                $line1 = $line1 === '' ? $word : $line1.' '.$word;
            } else {
                $line2 = $line2 === '' ? $word : $line2.' '.$word;
            }
        }

        if ($line2 === '') {
            return mb_substr($line1, 0, $maxLine - 1).'…';
        }

        if (mb_strlen($line2) > $maxLine) {
            $line2 = mb_substr($line2, 0, $maxLine - 1).'…';
        }

        return [$line1, $line2];
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
                        'maxRotation' => 0,
                        'minRotation' => 0,
                        'autoSkip' => false,
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
