<?php

namespace App\Filament\Widgets;

use App\Enums\EquipmentStatus;
use App\Enums\UserRole;
use App\Models\Equipment;
use App\Services\ComplianceCalculator;
use App\Services\DepreciationCalculator;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class DashboardStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int|array
    {
        return [
            'default' => 1,
            'sm' => 2,
            'lg' => 3,
            'xl' => 5,
        ];
    }

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

        $compactBookValue = $this->formatCompactRupiah($totalBookValue);
        $fullBookValue = 'Rp'.number_format((float) $totalBookValue, 0, ',', '.');

        return [
            Stat::make('Alat Aktif', number_format($activeCount, 0, ',', '.'))
                ->description('Unit terdaftar aktif')
                ->descriptionIcon(Heroicon::OutlinedCpuChip)
                ->color('primary'),
            Stat::make('Kepatuhan Kalibrasi', $compliance['formatted'])
                ->description("{$compliance['compliant_count']} dari {$compliance['required_count']} alat wajib")
                ->descriptionIcon(Heroicon::OutlinedShieldCheck)
                ->color($compliance['compliance_percent'] === null ? 'gray' : ($compliance['compliance_percent'] >= 80 ? 'success' : 'warning')),
            Stat::make('Alat Terlambat', number_format($overdueCount, 0, ',', '.'))
                ->description('Melewati jatuh tempo')
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color($overdueCount > 0 ? 'danger' : 'success'),
            Stat::make('Jatuh Tempo 30 Hari', number_format($dueSoonCount, 0, ',', '.'))
                ->description('Perlu dijadwalkan segera')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color($dueSoonCount > 0 ? 'warning' : 'success'),
            Stat::make('Total Nilai Buku', $compactBookValue)
                ->description($fullBookValue)
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('primary'),
        ];
    }

    private function formatCompactRupiah(string $amount): string
    {
        $value = (float) $amount;

        if ($value >= 1_000_000_000) {
            return 'Rp'.number_format($value / 1_000_000_000, 2, ',', '.').' M';
        }

        if ($value >= 1_000_000) {
            return 'Rp'.number_format($value / 1_000_000, 1, ',', '.').' jt';
        }

        return 'Rp'.number_format($value, 0, ',', '.');
    }
}
