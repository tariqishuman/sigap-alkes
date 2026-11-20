<?php

use App\Filament\Pages\AssetValueReport;
use App\Filament\Pages\CalibrationSchedule;
use App\Filament\Pages\OperationalReport;
use App\Filament\Resources\Calibrations\CalibrationResource;
use App\Filament\Resources\DamageReports\DamageReportResource;
use App\Filament\Resources\Equipment\EquipmentResource;
use App\Filament\Resources\EquipmentMovements\EquipmentMovementResource;
use App\Filament\Resources\EquipmentTypes\EquipmentTypeResource;
use App\Filament\Resources\Repairs\RepairResource;
use App\Filament\Resources\Rooms\RoomResource;
use App\Filament\Resources\SpareParts\SparePartResource;
use App\Filament\Resources\TestingInstitutions\TestingInstitutionResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('allows super_admin to access every resource and page even with intercept_gate set to after', function () {
    $superAdmin = User::where('email', 'superadmin@sigap-alkes.test')->firstOrFail();
    $this->actingAs($superAdmin);

    $urls = [
        '/admin',
        EquipmentResource::getUrl('index'),
        EquipmentResource::getUrl('create'),
        CalibrationSchedule::getUrl(),
        DamageReportResource::getUrl('index'),
        DamageReportResource::getUrl('create'),
        CalibrationResource::getUrl('index'),
        CalibrationResource::getUrl('create'),
        RepairResource::getUrl('index'),
        RepairResource::getUrl('create'),
        EquipmentMovementResource::getUrl('index'),
        EquipmentMovementResource::getUrl('create'),
        OperationalReport::getUrl(),
        AssetValueReport::getUrl(),
        RoomResource::getUrl('index'),
        EquipmentTypeResource::getUrl('index'),
        TestingInstitutionResource::getUrl('index'),
        SparePartResource::getUrl('index'),
        UserResource::getUrl('index'),
        RoleResource::getUrl('index'),
    ];

    foreach ($urls as $url) {
        $this->get($url)->assertOk();
    }
});

it('enforces the full PRD §3 role access matrix across all resources and pages', function (string $email, array $allowed, array $forbidden) {
    $user = User::where('email', $email)->firstOrFail();
    $this->actingAs($user);

    foreach ($allowed as $url) {
        $this->get($url)->assertOk();
    }

    foreach ($forbidden as $url) {
        $this->get($url)->assertForbidden();
    }
})->with([
    'admin_ipsrs' => fn () => [
        'admin@sigap-alkes.test',
        [
            '/admin',
            EquipmentResource::getUrl('index'),
            EquipmentResource::getUrl('create'),
            CalibrationSchedule::getUrl(),
            DamageReportResource::getUrl('index'),
            DamageReportResource::getUrl('create'),
            CalibrationResource::getUrl('index'),
            RepairResource::getUrl('index'),
            EquipmentMovementResource::getUrl('index'),
            OperationalReport::getUrl(),
            AssetValueReport::getUrl(),
            RoomResource::getUrl('index'),
            EquipmentTypeResource::getUrl('index'),
            TestingInstitutionResource::getUrl('index'),
            SparePartResource::getUrl('index'),
            UserResource::getUrl('index'),
        ],
        [
            CalibrationResource::getUrl('create'),
            RepairResource::getUrl('create'),
            EquipmentMovementResource::getUrl('create'),
            RoleResource::getUrl('index'),
        ],
    ],
    'teknisi' => fn () => [
        'teknisi@sigap-alkes.test',
        [
            '/admin',
            EquipmentResource::getUrl('index'),
            CalibrationSchedule::getUrl(),
            DamageReportResource::getUrl('index'),
            DamageReportResource::getUrl('create'),
            CalibrationResource::getUrl('index'),
            CalibrationResource::getUrl('create'),
            RepairResource::getUrl('index'),
            RepairResource::getUrl('create'),
            EquipmentMovementResource::getUrl('index'),
            EquipmentMovementResource::getUrl('create'),
            SparePartResource::getUrl('index'),
        ],
        [
            EquipmentResource::getUrl('create'),
            OperationalReport::getUrl(),
            AssetValueReport::getUrl(),
            RoomResource::getUrl('index'),
            EquipmentTypeResource::getUrl('index'),
            TestingInstitutionResource::getUrl('index'),
            SparePartResource::getUrl('create'),
            UserResource::getUrl('index'),
            RoleResource::getUrl('index'),
        ],
    ],
    'kepala_ruangan' => fn () => [
        'kepalaruangan@sigap-alkes.test',
        [
            '/admin',
            EquipmentResource::getUrl('index'),
            DamageReportResource::getUrl('index'),
            DamageReportResource::getUrl('create'),
        ],
        [
            EquipmentResource::getUrl('create'),
            CalibrationSchedule::getUrl(),
            CalibrationResource::getUrl('index'),
            RepairResource::getUrl('index'),
            EquipmentMovementResource::getUrl('index'),
            OperationalReport::getUrl(),
            AssetValueReport::getUrl(),
            RoomResource::getUrl('index'),
            EquipmentTypeResource::getUrl('index'),
            TestingInstitutionResource::getUrl('index'),
            SparePartResource::getUrl('index'),
            UserResource::getUrl('index'),
            RoleResource::getUrl('index'),
        ],
    ],
    'manajemen' => fn () => [
        'manajemen@sigap-alkes.test',
        [
            '/admin',
            CalibrationSchedule::getUrl(),
            DamageReportResource::getUrl('index'),
            CalibrationResource::getUrl('index'),
            RepairResource::getUrl('index'),
            EquipmentMovementResource::getUrl('index'),
            OperationalReport::getUrl(),
            AssetValueReport::getUrl(),
        ],
        [
            EquipmentResource::getUrl('index'),
            DamageReportResource::getUrl('create'),
            CalibrationResource::getUrl('create'),
            RepairResource::getUrl('create'),
            EquipmentMovementResource::getUrl('create'),
            RoomResource::getUrl('index'),
            EquipmentTypeResource::getUrl('index'),
            TestingInstitutionResource::getUrl('index'),
            SparePartResource::getUrl('index'),
            UserResource::getUrl('index'),
            RoleResource::getUrl('index'),
        ],
    ],
]);
