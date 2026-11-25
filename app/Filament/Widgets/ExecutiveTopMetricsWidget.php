<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Services\DashboardMetricsService;
use App\Services\PreventiveMaintenanceService;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class ExecutiveTopMetricsWidget extends Widget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.executive-top-metrics';

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
    protected function getViewData(): array
    {
        $period = (string) ($this->pageFilters['period'] ?? 'monthly');
        $date = $this->pageFilters['date'] ?? null;
        $roomId = filled($this->pageFilters['room_id'] ?? null) ? (int) $this->pageFilters['room_id'] : null;

        return [
            'metrics' => app(DashboardMetricsService::class)->executiveTopMetrics($period, is_string($date) ? $date : null, $roomId),
            'pmCompliance' => app(PreventiveMaintenanceService::class)->complianceRate($roomId),
        ];
    }
}
