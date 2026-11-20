<?php

namespace App\Filament\Widgets;

use App\Enums\EquipmentStatus;
use App\Enums\UserRole;
use App\Models\Equipment;
use App\Services\ComplianceCalculator;
use App\Services\DepreciationCalculator;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class DashboardStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Auth::user()?->hasAnyRole([
            UserRole::SuperAdmin->value,
            UserRole::AdminIpsrs->value,
            UserRole::Manajemen->value,
        ]) ?? false;
    }

    protected function getStats(): array
    {
        $activeEquipment = Equipment::query()
            ->where('is_active', true)
            ->with('equipmentType')
            ->get();

        $activeCount = $activeEquipment->count();
        $overdueCount = $activeEquipment->where('status', EquipmentStatus::Overdue)->count();

        $dueSoonCount = Equipment::query()
            ->where('is_active', true)
            ->dueBetween(today(), today()->addDays(30))
            ->count();

        $compliance = app(ComplianceCalculator::class)->calculate();
        $depreciation = app(DepreciationCalculator::class);

        $totalBookValue = '0.00';
        foreach ($activeEquipment as $equipment) {
            $bookValue = $depreciation->calculateForEquipment($equipment)['book_value'];
            $totalBookValue = bcadd($totalBookValue, $bookValue, 2);
        }

        return [
            Stat::make('Alat Aktif', number_format($activeCount, 0, ',', '.'))
                ->description('Unit terdaftar aktif'),
            Stat::make('Kepatuhan Kalibrasi', $compliance['formatted'])
                ->description("{$compliance['compliant_count']} dari {$compliance['required_count']} alat wajib kalibrasi")
                ->color($compliance['compliance_percent'] === null ? 'gray' : ($compliance['compliance_percent'] >= 80 ? 'success' : 'warning')),
            Stat::make('Alat Terlambat', number_format($overdueCount, 0, ',', '.'))
                ->description('Sertifikat melewati jatuh tempo')
                ->color($overdueCount > 0 ? 'danger' : 'success'),
            Stat::make('Jatuh Tempo 30 Hari ke Depan', number_format($dueSoonCount, 0, ',', '.'))
                ->description('Perlu penjadwalan segera')
                ->color($dueSoonCount > 0 ? 'warning' : 'success'),
            Stat::make('Total Nilai Buku Aset', 'Rp'.number_format((float) $totalBookValue, 0, ',', '.'))
                ->description('Seluruh alat aktif'),
        ];
    }
}
