<?php

namespace App\Support;

use App\Filament\Resources\Calibrations\CalibrationResource;
use App\Filament\Resources\DamageReports\DamageReportResource;
use App\Filament\Resources\Equipment\EquipmentResource;
use App\Filament\Resources\EquipmentMovements\EquipmentMovementResource;
use App\Filament\Resources\SpareParts\SparePartResource;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class QuickCreateMenu
{
    /**
     * Return quick "+ Tambah" actions permitted for the given user (design.md §2.2).
     *
     * @return array<int, array{key: string, label: string, url: string, icon: string}>
     */
    public static function items(?User $user = null): array
    {
        $user ??= Auth::user();

        if (! $user) {
            return [];
        }

        $items = [];

        if ($user->can('Create:DamageReport')) {
            $items[] = [
                'key' => 'damage_report',
                'label' => 'Lapor kerusakan',
                'url' => DamageReportResource::getUrl('create'),
                'icon' => 'heroicon-o-exclamation-triangle',
            ];
        }

        if ($user->can('Create:Calibration')) {
            $items[] = [
                'key' => 'calibration',
                'label' => 'Catat kalibrasi',
                'url' => CalibrationResource::getUrl('create'),
                'icon' => 'heroicon-o-shield-check',
            ];
        }

        if ($user->can('Create:Equipment')) {
            $items[] = [
                'key' => 'equipment',
                'label' => 'Tambah alat',
                'url' => EquipmentResource::getUrl('create'),
                'icon' => 'heroicon-o-cube',
            ];
        }

        if ($user->can('Create:EquipmentMovement')) {
            $items[] = [
                'key' => 'movement',
                'label' => 'Mutasi alat',
                'url' => EquipmentMovementResource::getUrl('create'),
                'icon' => 'heroicon-o-arrows-right-left',
            ];
        }

        if ($user->can('Update:SparePart')) {
            $items[] = [
                'key' => 'spare_part_stock',
                'label' => 'Tambah stok',
                'url' => SparePartResource::getUrl('index'),
                'icon' => 'heroicon-o-archive-box',
            ];
        }

        return $items;
    }
}
