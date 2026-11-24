<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Services\DashboardMetricsService;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class AssetValueByRoomWidget extends Widget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 5;

    protected static bool $isLazy = false;

    public function placeholder(): View
    {
        return view('filament.widgets.skeleton-card', [
            'variant' => 'bars',
            'title' => 'Memuat nilai buku per ruangan',
        ]);
    }

    protected string $view = 'filament.widgets.asset-value-by-room';

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'xl' => 4,
    ];

    public static function canView(): bool
    {
        return Auth::user()?->hasAnyRole([
            UserRole::SuperAdmin->value,
            UserRole::AdminIpsrs->value,
            UserRole::Manajemen->value,
        ]) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $roomId = filled($this->pageFilters['room_id'] ?? null) ? (int) $this->pageFilters['room_id'] : null;

        return [
            'data' => app(DashboardMetricsService::class)->bookValueByRoom($roomId),
        ];
    }
}
