<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Services\DashboardMetricsService;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class HeadNurseDashboardWidget extends Widget
{
    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.head-nurse-dashboard';

    public static function canView(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        return $user->hasRole(UserRole::KepalaRuangan->value)
            && ! $user->hasAnyRole([UserRole::SuperAdmin->value, UserRole::AdminIpsrs->value, UserRole::Manajemen->value]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = Auth::user();

        return [
            'data' => app(DashboardMetricsService::class)->headNurseSummary($user?->room_id, (int) $user?->id),
        ];
    }
}
