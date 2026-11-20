<?php

use App\Enums\CalibrationResult;
use App\Enums\EquipmentStatus;
use App\Enums\TestingInstitutionKind;
use App\Filament\Pages\AssetValueReport;
use App\Filament\Pages\OperationalReport;
use App\Filament\Resources\Equipment\Pages\ViewEquipment;
use App\Filament\Widgets\AssetValueByRoomChart;
use App\Filament\Widgets\DashboardStatsOverview;
use App\Filament\Widgets\MonthlyUptimeChart;
use App\Filament\Widgets\UpcomingCalibrationsWidget;
use App\Models\Calibration;
use App\Models\Equipment;
use App\Models\EquipmentType;
use App\Models\Room;
use App\Models\StatusHistory;
use App\Models\TestingInstitution;
use App\Models\User;
use App\Services\ComplianceCalculator;
use App\Services\DamageReportService;
use App\Services\DepreciationCalculator;
use App\Services\RepairService;
use App\Services\UptimeCalculator;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->travelTo(Carbon::create(2026, 11, 20, 12, 0, 0, 'Asia/Jakarta'));

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

    $this->typeNonCalibrated = EquipmentType::create([
        'name' => 'Tensimeter Non-Kalibrasi',
        'requires_calibration' => false,
        'requires_installation' => false,
        'calibration_interval_months' => 12,
        'useful_life_years' => 5,
        'residual_value_percent' => 10,
    ]);

    $this->institution = TestingInstitution::create([
        'name' => 'BPAFK Surabaya',
        'kind' => TestingInstitutionKind::Bpafk,
        'accreditation_number' => 'LK-045-IDN',
    ]);

    $this->superAdmin = User::where('email', 'superadmin@sigap-alkes.test')->firstOrFail();
    $this->admin = User::where('email', 'admin@sigap-alkes.test')->firstOrFail();
    $this->teknisi = User::where('email', 'teknisi@sigap-alkes.test')->firstOrFail();
    $this->kepalaIcu = User::where('email', 'kepalaruangan@sigap-alkes.test')->firstOrFail();
    $this->manajemen = User::where('email', 'manajemen@sigap-alkes.test')->firstOrFail();
});

function makeM4Equipment(
    EquipmentType $type,
    Room $room,
    string $code,
    string $cost = '30000000.00',
    ?string $residual = '3000000.00',
    string $acqDate = '2023-11-20',
    bool $isActive = true,
): Equipment {
    return Equipment::create([
        'inventory_code' => $code,
        'name' => "Alat {$code}",
        'equipment_type_id' => $type->id,
        'room_id' => $room->id,
        'brand' => 'Mindray',
        'model' => 'M4',
        'serial_number' => "SN-{$code}",
        'acquisition_date' => $acqDate,
        'acquisition_cost' => $cost,
        'residual_value' => $residual,
        'is_active' => $isActive,
    ]);
}

describe('DepreciationCalculator & Replacement Threshold (PRD §F6)', function () {
    it('passes the mandatory PRD §F6 straight-line depreciation test case (30 jt / 3 jt / 5 th)', function () {
        $calc = app(DepreciationCalculator::class);

        $at36 = $calc->calculate('30000000.00', '3000000.00', 5, 36);
        $at72 = $calc->calculate('30000000.00', '3000000.00', 5, 72);

        expect($at36['annual_depreciation'])->toBe('5400000.00')
            ->and($at36['accumulated_depreciation'])->toBe('16200000.00')
            ->and($at36['book_value'])->toBe('13800000.00')
            ->and($at72['book_value'])->toBe('3000000.00');
    });

    it('flags replacement at exactly the 50% boundary using bcmath multiplication, not below it', function () {
        $calc = app(DepreciationCalculator::class);

        // Equipment acquired 36 months ago: HP 30jt, NR 3jt, MM 5 -> NB = 13.800.000
        // 50% of 13.800.000 = 6.900.000
        $equipment = makeM4Equipment($this->typeNonCalibrated, $this->icuRoom, 'M4-DEP-001', '30000000.00', '3000000.00', '2023-11-20');

        expect($calc->shouldConsiderReplacement($equipment))->toBeFalse();

        // 1 cent below 50%: 6.899.999,99 -> false
        expect($calc->meetsReplacementThreshold('6899999.99', '13800000.00', 50))->toBeFalse();

        // Exactly at 50%: 6.900.000,00 -> true
        $report = app(DamageReportService::class)->report([
            'equipment_id' => $equipment->id,
            'complaint' => 'Kerusakan modul utama',
        ], $this->kepalaIcu);

        app(RepairService::class)->start([
            'damage_report_id' => $report->id,
            'started_at' => now()->subHours(4),
            'finished_at' => now()->subHour(),
            'action' => 'Penggantian papan sirkuit',
            'labor_cost' => '6900000.00',
        ], $this->teknisi->id);

        expect($calc->shouldConsiderReplacement($equipment->fresh()))->toBeTrue();

        // Higher configured threshold (60%) -> false
        config(['sigap.replace_threshold_percent' => 60]);
        expect($calc->shouldConsiderReplacement($equipment->fresh()))->toBeFalse();
    });
});

describe('UptimeCalculator (PRD §F5)', function () {
    it('calculates uptime with a mid-month repair and ignores under_testing as downtime', function () {
        // Equipment created before October 2026
        $this->travelTo(Carbon::create(2026, 9, 1, 0, 0, 0, 'Asia/Jakarta'));
        $equipment = makeM4Equipment($this->typeNonCalibrated, $this->icuRoom, 'M4-UP-001');

        // Clean initial history and construct deterministic timeline for October 2026 (31 days = 744 hours)
        $equipment->statusHistories()->delete();

        StatusHistory::create([
            'equipment_id' => $equipment->id,
            'from_status' => null,
            'to_status' => EquipmentStatus::FitForUse,
            'changed_at' => Carbon::create(2026, 9, 1, 0, 0, 0, 'Asia/Jakarta'),
            'reason' => 'Awal',
        ]);

        // UnderRepair from Oct 10 00:00 to Oct 13 00:00 -> 72 hours downtime
        StatusHistory::create([
            'equipment_id' => $equipment->id,
            'from_status' => EquipmentStatus::FitForUse,
            'to_status' => EquipmentStatus::UnderRepair,
            'changed_at' => Carbon::create(2026, 10, 10, 0, 0, 0, 'Asia/Jakarta'),
            'reason' => 'Perbaikan dimulai',
        ]);
        StatusHistory::create([
            'equipment_id' => $equipment->id,
            'from_status' => EquipmentStatus::UnderRepair,
            'to_status' => EquipmentStatus::UnderTesting,
            'changed_at' => Carbon::create(2026, 10, 13, 0, 0, 0, 'Asia/Jakarta'),
            'reason' => 'Mulai uji (bukan downtime)',
        ]);
        // UnderTesting from Oct 13 00:00 to Oct 15 00:00 -> 48 hours, MUST NOT count as downtime
        StatusHistory::create([
            'equipment_id' => $equipment->id,
            'from_status' => EquipmentStatus::UnderTesting,
            'to_status' => EquipmentStatus::FitForUse,
            'changed_at' => Carbon::create(2026, 10, 15, 0, 0, 0, 'Asia/Jakarta'),
            'reason' => 'Lulus uji',
        ]);

        $this->travelTo(Carbon::create(2026, 11, 20, 12, 0, 0, 'Asia/Jakarta'));

        $start = Carbon::create(2026, 10, 1, 0, 0, 0, 'Asia/Jakarta');
        $end = Carbon::create(2026, 11, 1, 0, 0, 0, 'Asia/Jakarta');

        $result = app(UptimeCalculator::class)->forEquipment($equipment->fresh(), $start, $end);

        // T = 744h, D = 72h -> (744 - 72) / 744 * 100 = 90.32%
        expect($result['total_hours'])->toBe(744.0)
            ->and($result['downtime_hours'])->toBe(72.0)
            ->and($result['uptime_percent'])->toBe(90.32);
    });

    it('starts T from equipment created_at when added mid-period, caps at now, excludes inactive, and aggregates as (ΣT−ΣD)/ΣT', function () {
        // Equipment A: created Nov 1 00:00, no downtime -> T = 19.5 days (468 hours) up to Nov 20 12:00
        $this->travelTo(Carbon::create(2026, 11, 1, 0, 0, 0, 'Asia/Jakarta'));
        $eqA = makeM4Equipment($this->typeNonCalibrated, $this->icuRoom, 'M4-UP-A');

        // Equipment B: created Nov 10 12:00 (mid-period) -> T = 10 days (240 hours) up to Nov 20 12:00
        // Unfit for 120 hours (Nov 15 12:00 to Nov 20 12:00) -> 50% individual uptime
        $this->travelTo(Carbon::create(2026, 11, 10, 12, 0, 0, 'Asia/Jakarta'));
        $eqB = makeM4Equipment($this->typeNonCalibrated, $this->icuRoom, 'M4-UP-B');

        StatusHistory::create([
            'equipment_id' => $eqB->id,
            'from_status' => EquipmentStatus::FitForUse,
            'to_status' => EquipmentStatus::Unfit,
            'changed_at' => Carbon::create(2026, 11, 15, 12, 0, 0, 'Asia/Jakarta'),
            'reason' => 'Tidak laik pakai',
        ]);

        // Equipment C: inactive -> must be excluded
        $eqInactive = makeM4Equipment($this->typeNonCalibrated, $this->icuRoom, 'M4-UP-C', isActive: false);

        $now = Carbon::create(2026, 11, 20, 12, 0, 0, 'Asia/Jakarta');
        $this->travelTo($now);

        $start = Carbon::create(2026, 11, 1, 0, 0, 0, 'Asia/Jakarta');
        $end = Carbon::create(2026, 11, 30, 23, 59, 59, 'Asia/Jakarta');

        $calc = app(UptimeCalculator::class);
        $metricsB = $calc->forEquipment($eqB->fresh(), $start, $end, $now);
        $metricsInactive = $calc->forEquipment($eqInactive->fresh(), $start, $end, $now);
        $agg = $calc->forCollection(collect([$eqA->fresh(), $eqB->fresh(), $eqInactive->fresh()]), $start, $end, $now);

        // ΣT = 468 + 240 = 708h, ΣD = 0 + 120 = 120h -> (708 - 120) / 708 = 83.05%
        // (Notice unweighted average of 100% and 50% would be 75% — must NOT be 75%!)
        expect($metricsB['total_hours'])->toBe(240.0)
            ->and($metricsB['downtime_hours'])->toBe(120.0)
            ->and($metricsB['uptime_percent'])->toBe(50.0)
            ->and($metricsInactive['total_seconds'])->toBe(0)
            ->and($agg['equipment_count'])->toBe(2)
            ->and($agg['total_hours'])->toBe(708.0)
            ->and($agg['downtime_hours'])->toBe(120.0)
            ->and($agg['uptime_percent'])->toBe(83.05);
    });
});

describe('ComplianceCalculator (PRD §F5)', function () {
    it('returns null and dash when Nw is zero without dividing by zero', function () {
        // Only non-calibrated equipment exists -> Nw = 0
        makeM4Equipment($this->typeNonCalibrated, $this->icuRoom, 'M4-COMP-000');

        $result = app(ComplianceCalculator::class)->calculate();

        expect($result['required_count'])->toBe(0)
            ->and($result['compliant_count'])->toBe(0)
            ->and($result['compliance_percent'])->toBeNull()
            ->and($result['formatted'])->toBe('–');
    });

    it('calculates today snapshot compliance accurately across active calibrated equipment', function () {
        $eqCompliant = makeM4Equipment($this->typeCalibrated, $this->icuRoom, 'M4-COMP-001');
        $eqExpired = makeM4Equipment($this->typeCalibrated, $this->icuRoom, 'M4-COMP-002');
        $eqNoCert = makeM4Equipment($this->typeCalibrated, $this->icuRoom, 'M4-COMP-003');
        $eqInactive = makeM4Equipment($this->typeCalibrated, $this->icuRoom, 'M4-COMP-004', isActive: false);

        Calibration::create([
            'equipment_id' => $eqCompliant->id,
            'testing_institution_id' => $this->institution->id,
            'tested_at' => today()->subMonths(2)->toDateString(),
            'result' => CalibrationResult::Pass,
            'certificate_number' => 'CERT-COMP-1',
            'valid_until' => today()->addMonths(10)->toDateString(),
        ]);

        Calibration::create([
            'equipment_id' => $eqExpired->id,
            'testing_institution_id' => $this->institution->id,
            'tested_at' => today()->subMonths(13)->toDateString(),
            'result' => CalibrationResult::Pass,
            'certificate_number' => 'CERT-COMP-2',
            'valid_until' => today()->subDay()->toDateString(),
        ]);

        // Nw = 3 active calibrated (eqCompliant, eqExpired, eqNoCert); Ns = 1 (eqCompliant)
        $result = app(ComplianceCalculator::class)->calculate();

        expect($result['required_count'])->toBe(3)
            ->and($result['compliant_count'])->toBe(1)
            ->and($result['compliance_percent'])->toBe(33.33)
            ->and($result['formatted'])->toBe('33,3%');
    });
});

describe('Asset Value Visibility, Dashboard & Reports UI (M4)', function () {
    it('hides the Nilai Aset card from kepala_ruangan and public QR page, but shows it with disclaimer to admin_ipsrs', function () {
        $equipment = makeM4Equipment($this->typeNonCalibrated, $this->icuRoom, 'M4-VIS-001', '30000000.00', '3000000.00', '2023-11-20');

        // Make repair cost reach 50% of 13.800.000 (= 6.900.000) to trigger "Pertimbangkan ganti"
        $report = app(DamageReportService::class)->report([
            'equipment_id' => $equipment->id,
            'complaint' => 'Kerusakan berat',
        ], $this->kepalaIcu);

        app(RepairService::class)->start([
            'damage_report_id' => $report->id,
            'started_at' => now()->subHours(2),
            'finished_at' => now()->subHour(),
            'action' => 'Perbaikan besar',
            'labor_cost' => '6900000.00',
        ], $this->teknisi->id);

        // Kepala ruangan must NOT see Nilai Aset, book value, disclaimer, or Pertimbangkan ganti
        $this->actingAs($this->kepalaIcu);
        Livewire::test(ViewEquipment::class, ['record' => $equipment->id])
            ->assertDontSee('Nilai Aset')
            ->assertDontSee('13.800.000')
            ->assertDontSee('Pertimbangkan ganti')
            ->assertDontSee('bukan pengganti pembukuan akuntansi resmi RS');

        // Admin IPSRS sees Nilai Aset, disclaimer, and Pertimbangkan ganti
        $this->flushSession();
        $this->actingAs($this->admin);
        Livewire::test(ViewEquipment::class, ['record' => $equipment->id])
            ->assertSee('Nilai Aset')
            ->assertSee('13.800.000')
            ->assertSee('Pertimbangkan ganti')
            ->assertSee('bukan pengganti pembukuan akuntansi resmi RS');

        // Public QR page must NOT show any asset value or replacement flag
        $this->get(route('public.equipment.show', ['qr_token' => $equipment->qr_token]))
            ->assertOk()
            ->assertDontSee('Nilai Aset')
            ->assertDontSee('13.800.000')
            ->assertDontSee('30.000.000')
            ->assertDontSee('Pertimbangkan ganti');
    });

    it('restricts dashboard widgets and report pages to manajemen, admin_ipsrs, and super_admin', function () {
        foreach ([$this->superAdmin, $this->admin, $this->manajemen] as $allowedUser) {
            $this->flushSession();
            $this->actingAs($allowedUser);

            expect(DashboardStatsOverview::canView())->toBeTrue()
                ->and(MonthlyUptimeChart::canView())->toBeTrue()
                ->and(UpcomingCalibrationsWidget::canView())->toBeTrue()
                ->and(AssetValueByRoomChart::canView())->toBeTrue();

            $this->get(OperationalReport::getUrl())->assertOk();
            $this->get(AssetValueReport::getUrl())->assertOk();
        }

        foreach ([$this->teknisi, $this->kepalaIcu] as $restrictedUser) {
            $this->flushSession();
            $this->actingAs($restrictedUser);

            expect(DashboardStatsOverview::canView())->toBeFalse()
                ->and(MonthlyUptimeChart::canView())->toBeFalse()
                ->and(UpcomingCalibrationsWidget::canView())->toBeFalse()
                ->and(AssetValueByRoomChart::canView())->toBeFalse();

            $this->get(OperationalReport::getUrl())->assertForbidden();
            $this->get(AssetValueReport::getUrl())->assertForbidden();
        }
    });

    it('renders operational report page, filters by room, and streams PDF reports', function () {
        $icuEq = makeM4Equipment($this->typeCalibrated, $this->icuRoom, 'M4-RPT-ICU');
        $igdEq = makeM4Equipment($this->typeCalibrated, $this->igdRoom, 'M4-RPT-IGD');

        $this->actingAs($this->manajemen);

        Livewire::test(OperationalReport::class)
            ->assertSee('Laporan Uptime')
            ->assertSee('Format kolom ekspor ASPAK bersifat sementara')
            ->assertCanSeeTableRecords([$icuEq, $igdEq])
            ->set('selectedRoomId', (string) $this->icuRoom->id)
            ->assertCanSeeTableRecords([$icuEq])
            ->assertCanNotSeeTableRecords([$igdEq])
            ->callAction('export_pdf')
            ->assertFileDownloaded();

        Livewire::test(AssetValueReport::class)
            ->assertSee('bukan pengganti pembukuan akuntansi resmi RS')
            ->assertCanSeeTableRecords([$icuEq, $igdEq])
            ->callAction('export_pdf')
            ->assertFileDownloaded();
    });
});
