<?php

use App\Enums\CalibrationResult;
use App\Enums\DamageReportStatus;
use App\Enums\EquipmentStatus;
use App\Enums\TestingInstitutionKind;
use App\Filament\Resources\DamageReports\DamageReportResource;
use App\Filament\Resources\DamageReports\Pages\CreateDamageReport;
use App\Filament\Resources\DamageReports\Pages\ListDamageReports;
use App\Filament\Resources\Equipment\Pages\ViewEquipment;
use App\Filament\Resources\EquipmentMovements\EquipmentMovementResource;
use App\Filament\Resources\EquipmentMovements\Pages\CreateEquipmentMovement;
use App\Filament\Resources\Repairs\Pages\CreateRepair;
use App\Filament\Resources\Repairs\Pages\ListRepairs;
use App\Filament\Resources\Repairs\RepairResource;
use App\Models\Calibration;
use App\Models\DamageReport;
use App\Models\Equipment;
use App\Models\EquipmentMovement;
use App\Models\EquipmentType;
use App\Models\Repair;
use App\Models\Room;
use App\Models\SparePart;
use App\Models\TestingInstitution;
use App\Models\User;
use App\Notifications\DamageReportedNotification;
use App\Services\DamageReportService;
use App\Services\EquipmentMovementService;
use App\Services\EquipmentStatusService;
use App\Services\RepairService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->travelTo(now()->setDate(2026, 11, 20)->setTime(10, 0));

    $this->icuRoom = Room::where('name', 'Ruang ICU')->firstOrFail();
    $this->igdRoom = Room::create(['name' => 'Ruang IGD', 'building' => 'Gedung Utama', 'floor' => 'Lantai 1']);

    $this->typeCalibrated = EquipmentType::create([
        'name' => 'Infusion Pump',
        'requires_calibration' => true,
        'requires_installation' => false,
        'calibration_interval_months' => 12,
        'useful_life_years' => 5,
        'residual_value_percent' => 10,
    ]);

    $this->typeInstalled = EquipmentType::create([
        'name' => 'Ventilator',
        'requires_calibration' => true,
        'requires_installation' => true,
        'calibration_interval_months' => 12,
        'useful_life_years' => 8,
        'residual_value_percent' => 10,
    ]);

    $this->typeNonCalibrated = EquipmentType::create([
        'name' => 'Tensimeter Non-Kalibrasi',
        'requires_calibration' => false,
        'requires_installation' => false,
        'calibration_interval_months' => 12,
        'useful_life_years' => 4,
        'residual_value_percent' => 0,
    ]);

    $this->institution = TestingInstitution::create([
        'name' => 'BPAFK Surabaya',
        'kind' => TestingInstitutionKind::Bpafk,
        'accreditation_number' => 'LK-045-IDN',
    ]);

    $this->battery = SparePart::create(['name' => 'Baterai Lithium-Ion 12V', 'unit' => 'pcs']);
    $this->sensor = SparePart::create(['name' => 'Sensor SpO2 Dewasa', 'unit' => 'pcs']);

    $this->superAdmin = User::where('email', 'superadmin@sigap-alkes.test')->firstOrFail();
    $this->admin = User::where('email', 'admin@sigap-alkes.test')->firstOrFail();
    $this->teknisi = User::where('email', 'teknisi@sigap-alkes.test')->firstOrFail();
    $this->kepalaIcu = User::where('email', 'kepalaruangan@sigap-alkes.test')->firstOrFail();
    $this->manajemen = User::where('email', 'manajemen@sigap-alkes.test')->firstOrFail();
});

function makeM3Equipment(EquipmentType $type, Room $room, string $code, bool $makeFit = false, ?TestingInstitution $inst = null, ?string $validUntil = null): Equipment
{
    $equipment = Equipment::create([
        'inventory_code' => $code,
        'name' => "Alat {$code}",
        'equipment_type_id' => $type->id,
        'room_id' => $room->id,
        'brand' => 'Mindray',
        'model' => 'X1',
        'acquisition_date' => '2024-01-01',
        'acquisition_cost' => '30000000.00',
        'is_active' => true,
    ]);

    if ($makeFit && $type->requires_calibration && $inst) {
        $statusService = app(EquipmentStatusService::class);
        $statusService->startTesting($equipment);
        Calibration::create([
            'equipment_id' => $equipment->id,
            'testing_institution_id' => $inst->id,
            'tested_at' => today()->subMonths(2)->toDateString(),
            'result' => CalibrationResult::Pass,
            'certificate_number' => "CERT-{$code}",
            'valid_until' => $validUntil ?? today()->addMonths(10)->toDateString(),
        ]);
        $statusService->recordTestResult($equipment, true);
        $equipment->refresh();
    }

    return $equipment;
}

describe('DamageReportService & DamageReportPolicy (PRD §F3)', function () {
    it('allows kepala_ruangan to report damage only for equipment in their own room without changing status', function () {
        Notification::fake();

        $icuEquipment = makeM3Equipment($this->typeCalibrated, $this->icuRoom, 'ICU-M3-001', true, $this->institution);
        $igdEquipment = makeM3Equipment($this->typeCalibrated, $this->igdRoom, 'IGD-M3-001', true, $this->institution);

        $service = app(DamageReportService::class);

        $report = $service->report([
            'equipment_id' => $icuEquipment->id,
            'complaint' => 'Layar berkedip saat dinyalakan',
        ], $this->kepalaIcu);

        expect($report->status)->toBe(DamageReportStatus::Open)
            ->and($report->reported_by)->toBe($this->kepalaIcu->id)
            ->and($icuEquipment->fresh()->status)->toBe(EquipmentStatus::FitForUse);

        Notification::assertSentTo([$this->teknisi, $this->admin], DamageReportedNotification::class);
        Notification::assertNotSentTo([$this->kepalaIcu, $this->manajemen], DamageReportedNotification::class);

        expect(fn () => $service->report([
            'equipment_id' => $igdEquipment->id,
            'complaint' => 'Alat ruangan lain',
        ], $this->kepalaIcu))->toThrow(ValidationException::class);
    });

    it('allows teknisi and admin_ipsrs to report damage on any active equipment', function () {
        $igdEquipment = makeM3Equipment($this->typeCalibrated, $this->igdRoom, 'IGD-M3-002');
        $service = app(DamageReportService::class);

        $byTeknisi = $service->report([
            'equipment_id' => $igdEquipment->id,
            'complaint' => 'Gagal uji kalibrasi, perlu perbaikan modul sensor',
        ], $this->teknisi);

        $byAdmin = $service->report([
            'equipment_id' => $igdEquipment->id,
            'complaint' => 'Kabel power terkelupas saat inspeksi',
        ], $this->admin);

        expect($byTeknisi->status)->toBe(DamageReportStatus::Open)
            ->and($byAdmin->status)->toBe(DamageReportStatus::Open);
    });

    it('allows only the reporter to edit complaint while open and forbids deletion for everyone', function () {
        $equipment = makeM3Equipment($this->typeCalibrated, $this->icuRoom, 'ICU-M3-003');
        $service = app(DamageReportService::class);

        $report = $service->report([
            'equipment_id' => $equipment->id,
            'complaint' => 'Keluhan awal',
        ], $this->kepalaIcu);

        expect($this->kepalaIcu->can('update', $report))->toBeTrue()
            ->and($this->teknisi->can('update', $report))->toBeFalse()
            ->and($this->admin->can('update', $report))->toBeFalse()
            ->and($this->superAdmin->can('delete', $report))->toBeFalse()
            ->and($this->admin->can('delete', $report))->toBeFalse()
            ->and($this->kepalaIcu->can('delete', $report))->toBeFalse();

        $updated = $service->update($report, ['complaint' => 'Keluhan diperbarui']);
        expect($updated->complaint)->toBe('Keluhan diperbarui');

        // Start repair -> report becomes in_progress -> editing is now forbidden
        app(RepairService::class)->start([
            'damage_report_id' => $report->id,
            'started_at' => now(),
        ], $this->teknisi->id);

        $report->refresh();

        expect($report->status)->toBe(DamageReportStatus::InProgress)
            ->and($this->kepalaIcu->can('update', $report))->toBeFalse()
            ->and(fn () => $service->update($report, ['complaint' => 'Terlambat diubah']))
            ->toThrow(ValidationException::class);
    });
});

describe('RepairService & RepairPolicy (PRD §F3 & §F4)', function () {
    it('transitions equipment to under_repair on start and calibration_due on finish for calibrated equipment with valid cert', function () {
        $equipment = makeM3Equipment($this->typeCalibrated, $this->icuRoom, 'ICU-REP-001', true, $this->institution, today()->addMonths(6)->toDateString());

        $report = app(DamageReportService::class)->report([
            'equipment_id' => $equipment->id,
            'complaint' => 'Alarm berbunyi terus menerus',
        ], $this->kepalaIcu);

        $repairService = app(RepairService::class);

        $repair = $repairService->start([
            'damage_report_id' => $report->id,
            'started_at' => now()->subHours(3),
            'action' => 'Cek papan sirkuit utama',
            'labor_cost' => '250000',
        ], $this->teknisi->id);

        expect($equipment->fresh()->status)->toBe(EquipmentStatus::UnderRepair)
            ->and($report->fresh()->status)->toBe(DamageReportStatus::InProgress)
            ->and($repair->isFinished())->toBeFalse();

        $finished = $repairService->finish($repair, [
            'finished_at' => now(),
            'action' => 'Ganti modul alarm dan uji fungsi',
        ], $this->teknisi->id);

        expect($finished->isFinished())->toBeTrue()
            ->and($report->fresh()->status)->toBe(DamageReportStatus::Resolved)
            ->and($equipment->fresh()->status)->toBe(EquipmentStatus::CalibrationDue);
    });

    it('transitions calibrated equipment with expired certificate to overdue after repair finishes', function () {
        $equipment = makeM3Equipment($this->typeCalibrated, $this->icuRoom, 'ICU-REP-002', true, $this->institution, today()->subDays(5)->toDateString());

        $report = app(DamageReportService::class)->report([
            'equipment_id' => $equipment->id,
            'complaint' => 'Pompa macet',
        ], $this->kepalaIcu);

        $repairService = app(RepairService::class);
        $repair = $repairService->start([
            'damage_report_id' => $report->id,
            'started_at' => now()->subHours(2),
            'finished_at' => now(),
            'action' => 'Pembersihan motor pompa',
        ], $this->teknisi->id);

        expect($repair->isFinished())->toBeTrue()
            ->and($report->fresh()->status)->toBe(DamageReportStatus::Resolved)
            ->and($equipment->fresh()->status)->toBe(EquipmentStatus::Overdue);
    });

    it('transitions non-calibrated equipment directly back to fit_for_use after repair finishes', function () {
        $equipment = makeM3Equipment($this->typeNonCalibrated, $this->icuRoom, 'ICU-REP-003');
        expect($equipment->status)->toBe(EquipmentStatus::FitForUse);

        $report = app(DamageReportService::class)->report([
            'equipment_id' => $equipment->id,
            'complaint' => 'Manset bocor',
        ], $this->kepalaIcu);

        $repairService = app(RepairService::class);
        $repair = $repairService->start([
            'damage_report_id' => $report->id,
            'started_at' => now()->subHour(),
            'finished_at' => now(),
            'action' => 'Ganti manset baru',
        ], $this->teknisi->id);

        expect($repair->isFinished())->toBeTrue()
            ->and($equipment->fresh()->status)->toBe(EquipmentStatus::FitForUse);
    });

    it('enforces at most one active repair per equipment', function () {
        $equipment = makeM3Equipment($this->typeCalibrated, $this->icuRoom, 'ICU-REP-004', true, $this->institution);
        $damageService = app(DamageReportService::class);
        $repairService = app(RepairService::class);

        $report1 = $damageService->report(['equipment_id' => $equipment->id, 'complaint' => 'Keluhan 1'], $this->kepalaIcu);
        $report2 = $damageService->report(['equipment_id' => $equipment->id, 'complaint' => 'Keluhan 2'], $this->kepalaIcu);

        $repairService->start([
            'damage_report_id' => $report1->id,
            'started_at' => now()->subHour(),
        ], $this->teknisi->id);

        expect(fn () => $repairService->start([
            'damage_report_id' => $report2->id,
            'started_at' => now(),
        ], $this->teknisi->id))->toThrow(ValidationException::class);
    });

    it('calculates repair cost and cumulative equipment repair cost with bcmath and snapshots spare part prices', function () {
        $equipment = makeM3Equipment($this->typeNonCalibrated, $this->icuRoom, 'ICU-REP-005');
        $damageService = app(DamageReportService::class);
        $repairService = app(RepairService::class);

        // Repair 1: labor 350.000 + (2 x 425.000) + (1 x 175.500,50) = 1.375.500,50
        $report1 = $damageService->report(['equipment_id' => $equipment->id, 'complaint' => 'Kerusakan 1'], $this->kepalaIcu);
        $repair1 = $repairService->start([
            'damage_report_id' => $report1->id,
            'started_at' => now()->subDays(5),
            'finished_at' => now()->subDays(4),
            'action' => 'Ganti baterai dan sensor',
            'labor_cost' => '350000.00',
            'spare_parts' => [
                ['spare_part_id' => $this->battery->id, 'quantity' => 2, 'unit_price' => '425000.00'],
                ['spare_part_id' => $this->sensor->id, 'quantity' => 1, 'unit_price' => '175500.50'],
            ],
        ], $this->teknisi->id);

        expect($repair1->sparePartsCost())->toBe('1025500.50')
            ->and($repair1->totalCost())->toBe('1375500.50');

        // Repair 2: labor 150.000 + (1 x 500.000) = 650.000 (different snapshot unit_price for same spare part!)
        $report2 = $damageService->report(['equipment_id' => $equipment->id, 'complaint' => 'Kerusakan 2'], $this->kepalaIcu);
        $repair2 = $repairService->start([
            'damage_report_id' => $report2->id,
            'started_at' => now()->subDay(),
            'finished_at' => now(),
            'action' => 'Ganti baterai batch baru',
            'labor_cost' => '150000.00',
            'spare_parts' => [
                ['spare_part_id' => $this->battery->id, 'quantity' => 1, 'unit_price' => '500000.00'],
            ],
        ], $this->teknisi->id);

        expect($repair2->totalCost())->toBe('650000.00')
            ->and($equipment->fresh()->totalRepairCost())->toBe('2025500.50');
    });

    it('validates finished_at >= started_at and locks finished repairs from editing or deleting', function () {
        $equipment = makeM3Equipment($this->typeNonCalibrated, $this->icuRoom, 'ICU-REP-006');
        $report = app(DamageReportService::class)->report(['equipment_id' => $equipment->id, 'complaint' => 'Error'], $this->kepalaIcu);
        $repairService = app(RepairService::class);

        expect(fn () => $repairService->start([
            'damage_report_id' => $report->id,
            'started_at' => now(),
            'finished_at' => now()->subHour(),
            'action' => 'Selesai sebelum mulai',
        ], $this->teknisi->id))->toThrow(ValidationException::class);

        $repair = $repairService->start([
            'damage_report_id' => $report->id,
            'started_at' => now()->subHour(),
            'finished_at' => now(),
            'action' => 'Selesai normal',
        ], $this->teknisi->id);

        expect($this->teknisi->can('update', $repair))->toBeFalse()
            ->and($this->superAdmin->can('update', $repair))->toBeFalse()
            ->and($this->superAdmin->can('delete', $repair))->toBeFalse()
            ->and($this->teknisi->can('delete', $repair))->toBeFalse()
            ->and(fn () => $repairService->update($repair, ['action' => 'Ubah setelah selesai'], $this->teknisi->id))
            ->toThrow(ValidationException::class);
    });
});

describe('EquipmentMovementService & EquipmentMovementPolicy (PRD §F3 & §F4)', function () {
    it('moves regular equipment without changing fit_for_use status and rejects moving to the same room', function () {
        $equipment = makeM3Equipment($this->typeCalibrated, $this->icuRoom, 'ICU-MOV-001', true, $this->institution);
        $service = app(EquipmentMovementService::class);

        expect(fn () => $service->move([
            'equipment_id' => $equipment->id,
            'to_room_id' => $this->icuRoom->id,
        ], $this->teknisi->id))->toThrow(ValidationException::class);

        $movement = $service->move([
            'equipment_id' => $equipment->id,
            'to_room_id' => $this->igdRoom->id,
            'is_reinstallation' => false,
            'notes' => 'Dipinjam ke IGD',
        ], $this->teknisi->id);

        expect($movement->from_room_id)->toBe($this->icuRoom->id)
            ->and($movement->to_room_id)->toBe($this->igdRoom->id)
            ->and($equipment->fresh()->room_id)->toBe($this->igdRoom->id)
            ->and($equipment->fresh()->status)->toBe(EquipmentStatus::FitForUse);
    });

    it('changes fit_for_use to calibration_due when type requires_installation or is_reinstallation is true', function () {
        $installedEq = makeM3Equipment($this->typeInstalled, $this->icuRoom, 'ICU-MOV-002', true, $this->institution);
        $regularEq = makeM3Equipment($this->typeCalibrated, $this->icuRoom, 'ICU-MOV-003', true, $this->institution);
        $service = app(EquipmentMovementService::class);

        $service->move([
            'equipment_id' => $installedEq->id,
            'to_room_id' => $this->igdRoom->id,
            'is_reinstallation' => false,
        ], $this->teknisi->id);

        $service->move([
            'equipment_id' => $regularEq->id,
            'to_room_id' => $this->igdRoom->id,
            'is_reinstallation' => true,
        ], $this->teknisi->id);

        expect($installedEq->fresh()->room_id)->toBe($this->igdRoom->id)
            ->and($installedEq->fresh()->status)->toBe(EquipmentStatus::CalibrationDue)
            ->and($regularEq->fresh()->room_id)->toBe($this->igdRoom->id)
            ->and($regularEq->fresh()->status)->toBe(EquipmentStatus::CalibrationDue);
    });

    it('keeps non-fit status unchanged during movement and forbids editing or deleting movements', function () {
        $equipment = makeM3Equipment($this->typeInstalled, $this->icuRoom, 'ICU-MOV-004');
        // Newly created calibrated equipment is CalibrationDue
        expect($equipment->status)->toBe(EquipmentStatus::CalibrationDue);

        $movement = app(EquipmentMovementService::class)->move([
            'equipment_id' => $equipment->id,
            'to_room_id' => $this->igdRoom->id,
            'is_reinstallation' => true,
        ], $this->teknisi->id);

        expect($equipment->fresh()->room_id)->toBe($this->igdRoom->id)
            ->and($equipment->fresh()->status)->toBe(EquipmentStatus::CalibrationDue)
            ->and($this->superAdmin->can('update', $movement))->toBeFalse()
            ->and($this->superAdmin->can('delete', $movement))->toBeFalse()
            ->and($this->teknisi->can('delete', $movement))->toBeFalse();
    });
});

describe('Filament UI, RBAC & Public QR Privacy (M3)', function () {
    it('scopes damage reports list for kepala_ruangan and supports status and room filters', function () {
        $icuEq = makeM3Equipment($this->typeCalibrated, $this->icuRoom, 'ICU-UI-001');
        $igdEq = makeM3Equipment($this->typeCalibrated, $this->igdRoom, 'IGD-UI-001');

        $icuReport = app(DamageReportService::class)->report(['equipment_id' => $icuEq->id, 'complaint' => 'Keluhan ICU'], $this->kepalaIcu);
        $igdReport = app(DamageReportService::class)->report(['equipment_id' => $igdEq->id, 'complaint' => 'Keluhan IGD'], $this->teknisi);

        // Kepala ruangan ICU only sees ICU report
        $this->actingAs($this->kepalaIcu);
        Livewire::test(ListDamageReports::class)
            ->assertCanSeeTableRecords([$icuReport])
            ->assertCanNotSeeTableRecords([$igdReport]);

        // Teknisi sees both and can filter by room and status
        $this->actingAs($this->teknisi);
        Livewire::test(ListDamageReports::class)
            ->assertCanSeeTableRecords([$icuReport, $igdReport])
            ->filterTable('room_id', $this->igdRoom->id)
            ->assertCanSeeTableRecords([$igdReport])
            ->assertCanNotSeeTableRecords([$icuReport])
            ->resetTableFilters()
            ->filterTable('status', DamageReportStatus::Open->value)
            ->assertCanSeeTableRecords([$icuReport, $igdReport]);
    });

    it('shows Laporkan kerusakan action on equipment detail for kepala_ruangan and creates report via form', function () {
        $icuEq = makeM3Equipment($this->typeCalibrated, $this->icuRoom, 'ICU-UI-002');

        $this->actingAs($this->kepalaIcu);

        Livewire::test(ViewEquipment::class, ['record' => $icuEq->id])
            ->assertActionVisible('report_damage')
            ->assertActionHidden('move_equipment');

        Livewire::test(CreateDamageReport::class)
            ->fillForm([
                'equipment_id' => $icuEq->id,
                'reported_at' => now()->format('Y-m-d H:i:s'),
                'complaint' => 'Tombol start tidak responsif',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(DamageReport::where('equipment_id', $icuEq->id)->where('complaint', 'Tombol start tidak responsif')->exists())->toBeTrue();
    });

    it('creates and finishes a repair and records a movement via Filament pages', function () {
        $equipment = makeM3Equipment($this->typeCalibrated, $this->icuRoom, 'ICU-UI-003', true, $this->institution);
        $report = app(DamageReportService::class)->report(['equipment_id' => $equipment->id, 'complaint' => 'Layar redup'], $this->kepalaIcu);

        $this->actingAs($this->teknisi);

        Livewire::test(CreateRepair::class)
            ->fillForm([
                'damage_report_id' => $report->id,
                'technician_id' => $this->teknisi->id,
                'started_at' => now()->subHour()->format('Y-m-d H:i:s'),
                'action' => 'Pengecekan backlight LCD',
                'labor_cost' => 200000,
                'spare_parts' => [
                    ['spare_part_id' => $this->battery->id, 'quantity' => 1, 'unit_price' => 300000],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $repair = Repair::where('damage_report_id', $report->id)->firstOrFail();
        expect($equipment->fresh()->status)->toBe(EquipmentStatus::UnderRepair)
            ->and($repair->totalCost())->toBe('500000.00');

        Livewire::test(ListRepairs::class)
            ->callTableAction('finish_repair', $repair, data: [
                'finished_at' => now()->format('Y-m-d H:i:s'),
                'action' => 'Ganti backlight dan baterai selesai',
            ])
            ->assertHasNoTableActionErrors();

        expect($repair->fresh()->isFinished())->toBeTrue()
            ->and($equipment->fresh()->status)->toBe(EquipmentStatus::CalibrationDue);

        Livewire::test(CreateEquipmentMovement::class)
            ->fillForm([
                'equipment_id' => $equipment->id,
                'to_room_id' => $this->igdRoom->id,
                'moved_at' => now()->format('Y-m-d H:i:s'),
                'is_reinstallation' => false,
                'notes' => 'Dipindah ke IGD',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(EquipmentMovement::where('equipment_id', $equipment->id)->where('to_room_id', $this->igdRoom->id)->exists())->toBeTrue()
            ->and($equipment->fresh()->room_id)->toBe($this->igdRoom->id);
    });

    it('enforces role access across M3 resources', function () {
        // kepala_ruangan cannot access RepairResource or EquipmentMovementResource
        $this->actingAs($this->kepalaIcu)
            ->get(RepairResource::getUrl('index'))->assertForbidden();
        $this->actingAs($this->kepalaIcu)
            ->get(EquipmentMovementResource::getUrl('index'))->assertForbidden();

        // manajemen can view lists, cannot create
        $this->flushSession();
        $this->actingAs($this->manajemen)
            ->get(DamageReportResource::getUrl('index'))->assertOk();
        $this->actingAs($this->manajemen)
            ->get(DamageReportResource::getUrl('create'))->assertForbidden();
        $this->actingAs($this->manajemen)
            ->get(RepairResource::getUrl('index'))->assertOk();
        $this->actingAs($this->manajemen)
            ->get(RepairResource::getUrl('create'))->assertForbidden();
        $this->actingAs($this->manajemen)
            ->get(EquipmentMovementResource::getUrl('index'))->assertOk();
        $this->actingAs($this->manajemen)
            ->get(EquipmentMovementResource::getUrl('create'))->assertForbidden();
    });

    it('does not leak repair cost, technician name, or damage complaint on the public QR page', function () {
        $equipment = makeM3Equipment($this->typeCalibrated, $this->icuRoom, 'ICU-QR-M3', true, $this->institution);
        $complaint = 'RAHASIA-KELUHAN-POWER-SUPPLY-RUSAK';

        $report = app(DamageReportService::class)->report([
            'equipment_id' => $equipment->id,
            'complaint' => $complaint,
        ], $this->kepalaIcu);

        app(RepairService::class)->start([
            'damage_report_id' => $report->id,
            'technician_id' => $this->teknisi->id,
            'started_at' => now()->subHours(2),
            'finished_at' => now(),
            'action' => 'Ganti power supply internal',
            'labor_cost' => '7654321.00',
        ], $this->teknisi->id);

        $this->get(route('public.equipment.show', ['qr_token' => $equipment->qr_token]))
            ->assertOk()
            ->assertSee($equipment->name)
            ->assertDontSee($complaint)
            ->assertDontSee($this->teknisi->name)
            ->assertDontSee('7.654.321')
            ->assertDontSee('7654321');
    });
});
