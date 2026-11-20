<?php

namespace App\Filament\Resources\Equipment\Widgets;

use App\Enums\EquipmentStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Equipment\EquipmentResource;
use App\Models\Equipment;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class EquipmentStatusBarWidget extends Widget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.equipment.status-bar-widget';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = Auth::user();
        $scopedRoomId = ($user && $user->hasRole(UserRole::KepalaRuangan->value) && ! $user->hasAnyRole([UserRole::SuperAdmin->value, UserRole::AdminIpsrs->value, UserRole::Manajemen->value]))
            ? $user->room_id
            : null;

        $equipment = Equipment::query()
            ->where('is_active', true)
            ->when($scopedRoomId, fn (Builder $q) => $q->where('room_id', $scopedRoomId))
            ->get(['status']);

        $counts = [];
        foreach (EquipmentStatus::cases() as $status) {
            $counts[$status->value] = $equipment->where('status', $status)->count();
        }

        return [
            'total' => $equipment->count(),
            'counts' => $counts,
            'filterUrlBase' => EquipmentResource::getUrl('index'),
        ];
    }
}
