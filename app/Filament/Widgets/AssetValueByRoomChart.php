<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Models\Room;
use App\Services\DepreciationCalculator;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class AssetValueByRoomChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Total Nilai Buku Aset per Ruangan (Juta Rp)';

    protected ?string $description = 'Estimasi nilai buku seluruh alat aktif';

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
        $calculator = app(DepreciationCalculator::class);

        $rooms = Room::query()
            ->with(['equipment' => fn ($q) => $q->where('is_active', true)->with('equipmentType')])
            ->orderBy('name')
            ->get();

        $labels = [];
        $values = [];

        foreach ($rooms as $room) {
            $roomTotal = '0.00';

            foreach ($room->equipment as $equipment) {
                $bookValue = $calculator->calculateForEquipment($equipment)['book_value'];
                $roomTotal = bcadd($roomTotal, $bookValue, 2);
            }

            $labels[] = $room->name;
            $values[] = round((float) $roomTotal / 1_000_000, 1);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Nilai Buku (Juta Rp)',
                    'data' => $values,
                    'backgroundColor' => '#0E7C7B',
                    'hoverBackgroundColor' => '#0B6463',
                    'borderRadius' => 6,
                    'maxBarThickness' => 52,
                ],
            ],
            'labels' => $labels,
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
                    'beginAtZero' => true,
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
        return 'bar';
    }
}
