<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Models\Room;
use App\Services\DepreciationCalculator;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class AssetValueByRoomChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Total Nilai Buku Aset per Ruangan (Rp)';

    protected ?string $description = 'Estimasi nilai buku metode garis lurus untuk alat aktif';

    protected int|string|array $columnSpan = 'full';

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
            $values[] = (float) $roomTotal;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Nilai Buku (Rp)',
                    'data' => $values,
                    'backgroundColor' => '#0E7C7B',
                    'borderRadius' => 6,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
