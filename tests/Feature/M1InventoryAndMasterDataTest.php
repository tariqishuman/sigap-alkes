<?php

use App\Enums\EquipmentStatus;
use App\Enums\TestingInstitutionKind;
use App\Filament\Resources\Equipment\EquipmentResource;
use App\Filament\Resources\Equipment\Pages\ViewEquipment;
use App\Models\Equipment;
use App\Models\EquipmentType;
use App\Models\Room;
use App\Models\SparePart;
use App\Models\TestingInstitution;
use App\Models\User;
use App\Services\DepreciationCalculator;
use App\Services\QrLabelService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Livewire\Livewire;

it('assigns initial status via EquipmentStatusService and records status_histories on creation', function () {
    $room = Room::create([
        'name' => 'Ruang ICU',
        'building' => 'Gedung Utama',
        'floor' => 'Lantai 2',
    ]);

    $calibratedType = EquipmentType::create([
        'name' => 'Infusion Pump',
        'requires_calibration' => true,
        'requires_installation' => false,
        'calibration_interval_months' => 12,
        'useful_life_years' => 5,
        'residual_value_percent' => 10,
    ]);

    $nonCalibratedType = EquipmentType::create([
        'name' => 'Stetoskop Manual',
        'requires_calibration' => false,
        'requires_installation' => false,
        'calibration_interval_months' => 12,
        'useful_life_years' => 5,
        'residual_value_percent' => 10,
    ]);

    $infusionPump = Equipment::create([
        'inventory_code' => 'ICU-001',
        'name' => 'Infusion Pump Terumo',
        'equipment_type_id' => $calibratedType->id,
        'room_id' => $room->id,
        'brand' => 'Terumo',
        'model' => 'TE-171',
        'serial_number' => 'SN-998877',
        'acquisition_date' => '2024-01-15',
        'acquisition_cost' => '30000000.00',
        'residual_value' => '3000000.00',
    ]);

    $stethoscope = Equipment::create([
        'inventory_code' => 'ICU-002',
        'name' => 'Stetoskop Littmann',
        'equipment_type_id' => $nonCalibratedType->id,
        'room_id' => $room->id,
        'acquisition_date' => '2024-01-15',
        'acquisition_cost' => '2500000.00',
    ]);

    expect($infusionPump->status)->toBe(EquipmentStatus::CalibrationDue)
        ->and(Str::isUuid($infusionPump->qr_token))->toBeTrue()
        ->and($infusionPump->statusHistories()->count())->toBe(1)
        ->and($infusionPump->statusHistories()->first()->to_status)->toBe(EquipmentStatus::CalibrationDue)
        ->and($stethoscope->status)->toBe(EquipmentStatus::FitForUse)
        ->and($stethoscope->statusHistories()->count())->toBe(1)
        ->and($stethoscope->statusHistories()->first()->to_status)->toBe(EquipmentStatus::FitForUse);
});

it('rejects duplicate inventory_code and forbids hard delete on equipment', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $room = Room::firstOrFail();
    $type = EquipmentType::create([
        'name' => 'Patient Monitor',
        'requires_calibration' => true,
        'requires_installation' => true,
        'calibration_interval_months' => 12,
        'useful_life_years' => 5,
        'residual_value_percent' => 10,
    ]);

    $equipment = Equipment::create([
        'inventory_code' => 'ICU-010',
        'name' => 'Patient Monitor Mindray',
        'equipment_type_id' => $type->id,
        'room_id' => $room->id,
        'acquisition_date' => '2025-01-01',
        'acquisition_cost' => '45000000.00',
    ]);

    $adminIpsrs = User::where('email', 'admin@sigap-alkes.test')->firstOrFail();
    expect($adminIpsrs->can('delete', $equipment))->toBeFalse();

    expect(fn () => Equipment::create([
        'inventory_code' => 'ICU-010',
        'name' => 'Duplikat Kode',
        'equipment_type_id' => $type->id,
        'room_id' => $room->id,
        'acquisition_date' => '2025-01-01',
        'acquisition_cost' => '10000000.00',
    ]))->toThrow(QueryException::class);
});

it('scopes kepala_ruangan to only view equipment in their assigned room', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $kepalaRuangan = User::where('email', 'kepalaruangan@sigap-alkes.test')->firstOrFail();
    $icuRoom = Room::where('name', 'Ruang ICU')->firstOrFail();

    expect($kepalaRuangan->room_id)->toBe($icuRoom->id);

    $igdRoom = Room::create([
        'name' => 'Ruang IGD',
        'building' => 'Gedung Utama',
        'floor' => 'Lantai 1',
    ]);

    $type = EquipmentType::create([
        'name' => 'Defibrillator',
        'requires_calibration' => true,
        'requires_installation' => false,
        'calibration_interval_months' => 12,
        'useful_life_years' => 5,
        'residual_value_percent' => 10,
    ]);

    $icuEquipment = Equipment::create([
        'inventory_code' => 'ICU-DEF-01',
        'name' => 'Defibrillator ICU',
        'equipment_type_id' => $type->id,
        'room_id' => $icuRoom->id,
        'acquisition_date' => '2025-02-01',
        'acquisition_cost' => '75000000.00',
    ]);

    $igdEquipment = Equipment::create([
        'inventory_code' => 'IGD-DEF-01',
        'name' => 'Defibrillator IGD',
        'equipment_type_id' => $type->id,
        'room_id' => $igdRoom->id,
        'acquisition_date' => '2025-02-01',
        'acquisition_cost' => '75000000.00',
    ]);

    $this->actingAs($kepalaRuangan);

    $visibleIds = EquipmentResource::getEloquentQuery()->pluck('id')->all();
    expect($visibleIds)->toContain($icuEquipment->id)
        ->and($visibleIds)->not->toContain($igdEquipment->id);

    expect($kepalaRuangan->can('view', $icuEquipment))->toBeTrue()
        ->and($kepalaRuangan->can('view', $igdEquipment))->toBeFalse();

    $this->get('/admin/equipment')
        ->assertOk()
        ->assertSee('Defibrillator ICU')
        ->assertDontSee('Defibrillator IGD');

    $this->get("/admin/equipment/{$icuEquipment->id}")
        ->assertOk()
        ->assertSee('Defibrillator ICU');

    $this->get("/admin/equipment/{$igdEquipment->id}")
        ->assertNotFound();
});

it('enforces Shield permissions per role for all M1 resources (403 for unauthorized roles)', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $icuRoom = Room::where('name', 'Ruang ICU')->firstOrFail();
    $type = EquipmentType::create([
        'name' => 'Syringe Pump',
        'requires_calibration' => true,
        'requires_installation' => false,
        'calibration_interval_months' => 12,
        'useful_life_years' => 5,
        'residual_value_percent' => 10,
    ]);
    TestingInstitution::create([
        'name' => 'BPAFK Surabaya',
        'kind' => TestingInstitutionKind::Bpafk,
        'accreditation_number' => 'LK-001-IDN',
    ]);
    SparePart::create([
        'name' => 'Filter Hepa',
        'unit' => 'pcs',
    ]);
    $equipment = Equipment::create([
        'inventory_code' => 'ICU-SYR-01',
        'name' => 'Syringe Pump B.Braun',
        'equipment_type_id' => $type->id,
        'room_id' => $icuRoom->id,
        'acquisition_date' => '2025-01-10',
        'acquisition_cost' => '22000000.00',
    ]);

    $adminIpsrs = User::where('email', 'admin@sigap-alkes.test')->firstOrFail();
    $teknisi = User::where('email', 'teknisi@sigap-alkes.test')->firstOrFail();
    $kepalaRuangan = User::where('email', 'kepalaruangan@sigap-alkes.test')->firstOrFail();
    $manajemen = User::where('email', 'manajemen@sigap-alkes.test')->firstOrFail();

    // admin_ipsrs: full access to Master Data & Equipment
    $this->flushSession();
    $this->actingAs($adminIpsrs)->get('/admin/rooms')->assertOk();
    $this->actingAs($adminIpsrs)->get('/admin/equipment-types')->assertOk();
    $this->actingAs($adminIpsrs)->get('/admin/testing-institutions')->assertOk();
    $this->actingAs($adminIpsrs)->get('/admin/spare-parts')->assertOk();
    $this->actingAs($adminIpsrs)->get('/admin/equipment')->assertOk();
    $this->actingAs($adminIpsrs)->get('/admin/equipment/create')->assertOk();
    $this->actingAs($adminIpsrs)->get("/admin/equipment/{$equipment->id}/edit")->assertOk();

    // teknisi: can view all equipment, 403 on create/edit equipment & all Master Data
    $this->flushSession();
    $this->actingAs($teknisi)->get('/admin/equipment')->assertOk();
    $this->actingAs($teknisi)->get("/admin/equipment/{$equipment->id}")->assertOk();
    $this->actingAs($teknisi)->get('/admin/equipment/create')->assertForbidden();
    $this->actingAs($teknisi)->get("/admin/equipment/{$equipment->id}/edit")->assertForbidden();
    $this->actingAs($teknisi)->get('/admin/rooms')->assertForbidden();
    $this->actingAs($teknisi)->get('/admin/equipment-types')->assertForbidden();
    $this->actingAs($teknisi)->get('/admin/testing-institutions')->assertForbidden();
    $this->actingAs($teknisi)->get('/admin/spare-parts')->assertForbidden();

    // kepala_ruangan: can view equipment list, 403 on create/edit equipment & all Master Data
    $this->flushSession();
    $this->actingAs($kepalaRuangan)->get('/admin/equipment')->assertOk();
    $this->actingAs($kepalaRuangan)->get('/admin/equipment/create')->assertForbidden();
    $this->actingAs($kepalaRuangan)->get("/admin/equipment/{$equipment->id}/edit")->assertForbidden();
    $this->actingAs($kepalaRuangan)->get('/admin/rooms')->assertForbidden();
    $this->actingAs($kepalaRuangan)->get('/admin/equipment-types')->assertForbidden();
    $this->actingAs($kepalaRuangan)->get('/admin/testing-institutions')->assertForbidden();
    $this->actingAs($kepalaRuangan)->get('/admin/spare-parts')->assertForbidden();

    // manajemen: 403 on Master Data & Equipment CRUD
    $this->flushSession();
    $this->actingAs($manajemen)->get('/admin/rooms')->assertForbidden();
    $this->actingAs($manajemen)->get('/admin/equipment-types')->assertForbidden();
    $this->actingAs($manajemen)->get('/admin/testing-institutions')->assertForbidden();
    $this->actingAs($manajemen)->get('/admin/spare-parts')->assertForbidden();
    $this->actingAs($manajemen)->get('/admin/equipment/create')->assertForbidden();
});

it('renders public QR page without leaking acquisition_cost, residual_value, or user data', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $icuRoom = Room::where('name', 'Ruang ICU')->firstOrFail();
    $type = EquipmentType::create([
        'name' => 'Ventilator',
        'requires_calibration' => true,
        'requires_installation' => true,
        'calibration_interval_months' => 12,
        'useful_life_years' => 5,
        'residual_value_percent' => 10,
    ]);

    $equipment = Equipment::create([
        'inventory_code' => 'ICU-VENT-99',
        'name' => 'Ventilator Hamilton C1',
        'equipment_type_id' => $type->id,
        'room_id' => $icuRoom->id,
        'brand' => 'Hamilton',
        'model' => 'C1',
        'serial_number' => 'SN-SECRET-991122',
        'acquisition_date' => '2024-05-10',
        'acquisition_cost' => '345678900.00',
        'residual_value' => '34567890.00',
        'notes' => 'Catatan internal rahasia teknisi',
    ]);

    $response = $this->get("/alat/{$equipment->qr_token}");

    $response->assertOk()
        ->assertSee('RS When yh')
        ->assertSee('ICU-VENT-99')
        ->assertSee('Ventilator Hamilton C1')
        ->assertSee('Hamilton C1')
        ->assertSee('Ruang ICU')
        ->assertSee('PERLU KALIBRASI')
        ->assertDontSee('345678900')
        ->assertDontSee('345.678.900')
        ->assertDontSee('34567890')
        ->assertDontSee('34.567.890')
        ->assertDontSee('SN-SECRET-991122')
        ->assertDontSee('Catatan internal rahasia teknisi')
        ->assertDontSee('Budi Santoso')
        ->assertDontSee('Andi Pratama')
        ->assertDontSee('Ns. Siti Aminah')
        ->assertDontSee('admin@sigap-alkes.test');

    // Test danger alert for Overdue / Unfit status
    $equipment->forceFill(['status' => EquipmentStatus::Overdue])->saveQuietly();

    $this->get("/alat/{$equipment->qr_token}")
        ->assertOk()
        ->assertSee('TERLAMBAT')
        ->assertSee('Jangan gunakan alat ini. Hubungi IPSRS.');

    // Invalid token returns 404
    $this->get('/alat/not-a-valid-uuid')->assertNotFound();
    $this->get('/alat/'.Str::uuid())->assertNotFound();
});

it('generates QR label PDF for single and bulk equipment', function () {
    $room = Room::create([
        'name' => 'Ruang Operasi',
        'building' => 'Gedung Bedah',
        'floor' => 'Lantai 3',
    ]);
    $type = EquipmentType::create([
        'name' => 'Anesthesia Machine',
        'requires_calibration' => true,
        'requires_installation' => true,
        'calibration_interval_months' => 12,
        'useful_life_years' => 8,
        'residual_value_percent' => 10,
    ]);

    $eq1 = Equipment::create([
        'inventory_code' => 'OK-001',
        'name' => 'Mesin Anestesi Drager',
        'equipment_type_id' => $type->id,
        'room_id' => $room->id,
        'acquisition_date' => '2024-01-01',
        'acquisition_cost' => '250000000.00',
    ]);
    $eq2 = Equipment::create([
        'inventory_code' => 'OK-002',
        'name' => 'Lampu Operasi LED',
        'equipment_type_id' => $type->id,
        'room_id' => $room->id,
        'acquisition_date' => '2024-01-01',
        'acquisition_cost' => '80000000.00',
    ]);

    $service = app(QrLabelService::class);
    $pdfOutput = $service->generateLabelsPdf(collect([$eq1, $eq2]))->output();

    expect($pdfOutput)->toStartWith('%PDF-');

    $this->seed(RoleAndPermissionSeeder::class);
    $admin = User::where('email', 'admin@sigap-alkes.test')->firstOrFail();
    $this->actingAs($admin);

    Livewire::test(ViewEquipment::class, ['record' => $eq1->getRouteKey()])
        ->callAction('print_qr')
        ->assertFileDownloaded("label-qr-{$eq1->inventory_code}.pdf");
});

it('calculates straight-line depreciation accurately for PRD §F6 mandatory test case', function () {
    $calculator = app(DepreciationCalculator::class);

    // HP = 30.000.000, NR = 3.000.000, MM = 5 -> P = 5.400.000/yr; 36 mos NB = 13.800.000; 72 mos NB = 3.000.000
    $at36Months = $calculator->calculate(
        acquisitionCost: '30000000.00',
        residualValue: '3000000.00',
        usefulLifeYears: 5,
        fullMonthsElapsed: 36,
    );

    expect($at36Months['annual_depreciation'])->toBe('5400000.00')
        ->and($at36Months['accumulated_depreciation'])->toBe('16200000.00')
        ->and($at36Months['book_value'])->toBe('13800000.00');

    $at72Months = $calculator->calculate(
        acquisitionCost: '30000000.00',
        residualValue: '3000000.00',
        usefulLifeYears: 5,
        fullMonthsElapsed: 72,
    );

    expect($at72Months['annual_depreciation'])->toBe('5400000.00')
        ->and($at72Months['book_value'])->toBe('3000000.00');
});
