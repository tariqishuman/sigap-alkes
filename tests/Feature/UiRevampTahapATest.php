<?php

use App\Enums\EquipmentStatus;
use App\Filament\Resources\Equipment\EquipmentResource;
use App\Models\User;
use App\Support\QuickCreateMenu;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;

uses(RefreshDatabase::class);

it('renders sigap.status-badge with dot, Indonesian label, and v2 palette for all 6 equipment statuses', function (EquipmentStatus $status, string $expectedBg, string $expectedText) {
    $html = Blade::render('<x-sigap.status-badge :status="$status" />', ['status' => $status]);

    expect($html)
        ->toContain(e($status->getLabel()))
        ->toContain("background-color: {$expectedBg}")
        ->toContain("color: {$expectedText}")
        ->toContain('rounded-full');
})->with([
    'fit_for_use' => [EquipmentStatus::FitForUse, '#E7F5EE', '#22774F'],
    'calibration_due' => [EquipmentStatus::CalibrationDue, '#FCF3E2', '#A66F12'],
    'overdue' => [EquipmentStatus::Overdue, '#FBEAEA', '#B83C38'],
    'under_testing' => [EquipmentStatus::UnderTesting, '#EAF1FE', '#2563EB'],
    'under_repair' => [EquipmentStatus::UnderRepair, '#EFF2F2', '#55666A'],
    'unfit' => [EquipmentStatus::Unfit, '#FBEAEA', '#9A2F2C'],
]);

it('filters the topbar "+ Tambah" quick-create menu strictly by role permissions', function (string $email, array $expectedKeys) {
    $this->seed(RoleAndPermissionSeeder::class);
    $user = User::where('email', $email)->firstOrFail();

    $keys = array_column(QuickCreateMenu::items($user), 'key');

    expect($keys)->toBe($expectedKeys);
})->with([
    'super_admin' => ['superadmin@sigap-alkes.test', ['damage_report', 'calibration', 'equipment', 'movement', 'spare_part_stock']],
    'admin_ipsrs' => ['admin@sigap-alkes.test', ['damage_report', 'equipment', 'spare_part_stock']],
    'teknisi' => ['teknisi@sigap-alkes.test', ['damage_report', 'calibration', 'movement']],
    'kepala_ruangan' => ['kepalaruangan@sigap-alkes.test', ['damage_report']],
    'manajemen' => ['manajemen@sigap-alkes.test', []],
    'badan_kalibrasi' => ['kalibrasi@sigap-alkes.test', ['calibration']],
    'magang' => ['magang@sigap-alkes.test', ['damage_report']],
]);

it('configures EquipmentResource global search across name, inventory_code, and serial_number', function () {
    expect(EquipmentResource::getGloballySearchableAttributes())
        ->toBe(['name', 'inventory_code', 'serial_number']);
});
