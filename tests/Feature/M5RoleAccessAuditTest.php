<?php

use App\Enums\CalibrationResult;
use App\Enums\TestingInstitutionKind;
use App\Filament\Pages\AssetValueReport;
use App\Filament\Pages\CalibrationSchedule;
use App\Filament\Pages\OperationalReport;
use App\Filament\Resources\Calibrations\CalibrationResource;
use App\Filament\Resources\DamageReports\DamageReportResource;
use App\Filament\Resources\Equipment\EquipmentResource;
use App\Filament\Resources\Equipment\Pages\ViewEquipment;
use App\Filament\Resources\EquipmentMovements\EquipmentMovementResource;
use App\Filament\Resources\EquipmentTypes\EquipmentTypeResource;
use App\Filament\Resources\Repairs\Pages\ViewRepair;
use App\Filament\Resources\Repairs\RepairResource;
use App\Filament\Resources\Rooms\RoomResource;
use App\Filament\Resources\SpareParts\SparePartResource;
use App\Filament\Resources\TestingInstitutions\TestingInstitutionResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Equipment;
use App\Models\EquipmentType;
use App\Models\Room;
use App\Models\SparePart;
use App\Models\TestingInstitution;
use App\Models\User;
use App\Services\CalibrationService;
use App\Services\DamageReportService;
use App\Services\RepairService;
use App\Services\SparePartStockService;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

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
    'badan_kalibrasi' => fn () => [
        'kalibrasi@sigap-alkes.test',
        [
            '/admin',
            EquipmentResource::getUrl('index'),
            CalibrationSchedule::getUrl(),
            CalibrationResource::getUrl('index'),
            CalibrationResource::getUrl('create'),
        ],
        [
            EquipmentResource::getUrl('create'),
            DamageReportResource::getUrl('index'),
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
    'magang' => fn () => [
        'magang@sigap-alkes.test',
        [
            '/admin',
            EquipmentResource::getUrl('index'),
            CalibrationSchedule::getUrl(),
            CalibrationResource::getUrl('index'),
            DamageReportResource::getUrl('index'),
            DamageReportResource::getUrl('create'),
            RepairResource::getUrl('index'),
            EquipmentMovementResource::getUrl('index'),
        ],
        [
            EquipmentResource::getUrl('create'),
            CalibrationResource::getUrl('create'),
            RepairResource::getUrl('create'),
            EquipmentMovementResource::getUrl('create'),
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
]);

it('prevents badan_kalibrasi from recording or editing calibrations for another testing institution', function () {
    $badan = User::where('email', 'kalibrasi@sigap-alkes.test')->firstOrFail();
    $teknisi = User::where('email', 'teknisi@sigap-alkes.test')->firstOrFail();
    $bpafk = TestingInstitution::where('name', 'BPAFK Surabaya')->firstOrFail();
    $otherInst = TestingInstitution::create([
        'name' => 'Lab Swasta Lain',
        'kind' => TestingInstitutionKind::Swasta,
        'accreditation_number' => 'LK-999-IDN',
    ]);

    $room = Room::where('name', 'Ruang ICU')->firstOrFail();
    $type = EquipmentType::create([
        'name' => 'Infusion Pump',
        'requires_calibration' => true,
        'requires_installation' => false,
        'calibration_interval_months' => 12,
        'useful_life_years' => 5,
        'residual_value_percent' => 10,
    ]);

    $eq1 = Equipment::create([
        'inventory_code' => 'BK-001',
        'name' => 'Infusion Pump BK 1',
        'equipment_type_id' => $type->id,
        'room_id' => $room->id,
        'acquisition_date' => '2025-01-01',
        'acquisition_cost' => '30000000.00',
    ]);

    $calService = app(CalibrationService::class);

    // Rejects recording under another institution
    expect(fn () => $calService->record([
        'equipment_id' => $eq1->id,
        'testing_institution_id' => $otherInst->id,
        'tested_at' => today()->toDateString(),
        'result' => CalibrationResult::Pass->value,
        'certificate_number' => 'CERT-WRONG-01',
        'valid_until' => today()->addMonths(12)->toDateString(),
    ], $badan->id))->toThrow(ValidationException::class);

    // Allows recording under own institution (auto-filled when omitted)
    $ownCal = $calService->record([
        'equipment_id' => $eq1->id,
        'tested_at' => today()->toDateString(),
        'result' => CalibrationResult::Pass->value,
        'certificate_number' => 'CERT-OWN-01',
        'valid_until' => today()->addMonths(12)->toDateString(),
    ], $badan->id);

    expect($ownCal->testing_institution_id)->toBe($bpafk->id)
        ->and($badan->can('update', $ownCal))->toBeTrue();

    // Calibration belonging to another institution cannot be edited by badan_kalibrasi
    $otherCal = $calService->record([
        'equipment_id' => $eq1->id,
        'testing_institution_id' => $otherInst->id,
        'tested_at' => today()->toDateString(),
        'result' => CalibrationResult::Pass->value,
        'certificate_number' => 'CERT-OTHER-01',
        'valid_until' => today()->addMonths(12)->toDateString(),
    ], $teknisi->id);

    expect($badan->can('update', $otherCal))->toBeFalse()
        ->and(fn () => $calService->update($otherCal, [
            'tested_at' => today()->toDateString(),
            'certificate_number' => 'CERT-HIJACK',
            'valid_until' => today()->addMonths(12)->toDateString(),
        ], $badan->id))->toThrow(ValidationException::class);
});

it('hides asset values and repair costs from magang and badan_kalibrasi while allowing magang to report damage in any room', function () {
    $magang = User::where('email', 'magang@sigap-alkes.test')->firstOrFail();
    $badan = User::where('email', 'kalibrasi@sigap-alkes.test')->firstOrFail();
    $admin = User::where('email', 'admin@sigap-alkes.test')->firstOrFail();
    $teknisi = User::where('email', 'teknisi@sigap-alkes.test')->firstOrFail();

    $igdRoom = Room::create(['name' => 'Ruang IGD', 'building' => 'Gedung Utama', 'floor' => 'Lantai 1']);
    $type = EquipmentType::create([
        'name' => 'Tensimeter Non-Kalibrasi',
        'requires_calibration' => false,
        'requires_installation' => false,
        'calibration_interval_months' => 12,
        'useful_life_years' => 5,
        'residual_value_percent' => 10,
    ]);

    $equipment = Equipment::create([
        'inventory_code' => 'MG-001',
        'name' => 'Alat Magang',
        'equipment_type_id' => $type->id,
        'room_id' => $igdRoom->id,
        'acquisition_date' => '2024-01-01',
        'acquisition_cost' => '30000000.00',
    ]);

    $part = SparePart::create(['name' => 'Manset', 'unit' => 'pcs', 'min_stock' => 1]);
    app(SparePartStockService::class)->stockIn($part, 5, 'Stok awal', $admin->id);

    // Magang can create a damage report for any room
    $report = app(DamageReportService::class)->report([
        'equipment_id' => $equipment->id,
        'complaint' => 'Layar buram',
    ], $magang);

    $repair = app(RepairService::class)->start([
        'damage_report_id' => $report->id,
        'started_at' => now()->subHour(),
        'finished_at' => now(),
        'action' => 'Ganti manset',
        'labor_cost' => '987654.00',
        'spare_parts' => [
            ['spare_part_id' => $part->id, 'quantity' => 1, 'unit_price' => '234567.00'],
        ],
    ], $teknisi->id);

    // Magang does NOT see asset values or repair costs
    $this->actingAs($magang);
    Livewire::test(ViewEquipment::class, ['record' => $equipment->id])
        ->assertDontSee('Nilai Aset')
        ->assertDontSee('30.000.000');

    Livewire::test(ViewRepair::class, ['record' => $repair->id])
        ->assertSee('Ganti manset')
        ->assertDontSee('Rincian Biaya')
        ->assertDontSee('987.654')
        ->assertDontSee('234.567');

    // Badan kalibrasi does NOT see asset values
    $this->flushSession();
    $this->actingAs($badan);
    Livewire::test(ViewEquipment::class, ['record' => $equipment->id])
        ->assertDontSee('Nilai Aset')
        ->assertDontSee('30.000.000');
});

it('grants demo role read-only access to all menus, reports, and master data without write access', function () {
    $demo = User::where('email', 'demo@sigap-alkes.test')->firstOrFail();
    $this->actingAs($demo);

    $allowedUrls = [
        '/admin',
        EquipmentResource::getUrl('index'),
        CalibrationSchedule::getUrl(),
        DamageReportResource::getUrl('index'),
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
    ];

    foreach ($allowedUrls as $url) {
        $this->get($url)->assertOk();
    }

    $forbiddenUrls = [
        EquipmentResource::getUrl('create'),
        DamageReportResource::getUrl('create'),
        CalibrationResource::getUrl('create'),
        RepairResource::getUrl('create'),
        EquipmentMovementResource::getUrl('create'),
        RoomResource::getUrl('create'),
        EquipmentTypeResource::getUrl('create'),
        TestingInstitutionResource::getUrl('create'),
        SparePartResource::getUrl('create'),
        UserResource::getUrl('create'),
        RoleResource::getUrl('create'),
        '/admin/pengaturan-aplikasi',
    ];

    foreach ($forbiddenUrls as $url) {
        $this->get($url)->assertForbidden();
    }

    expect($demo->canViewCosts())->toBeTrue();
});
