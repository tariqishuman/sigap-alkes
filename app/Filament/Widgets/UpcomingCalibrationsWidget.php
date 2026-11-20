<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Filament\Pages\CalibrationSchedule;
use App\Services\DashboardMetricsService;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class UpcomingCalibrationsWidget extends Widget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 3;

    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.upcoming-calibrations';

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'xl' => 3,
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
            'items' => app(DashboardMetricsService::class)->upcomingCalibrations($roomId, 6),
            'viewAllUrl' => CalibrationSchedule::getUrl(),
        ];
    }
}
