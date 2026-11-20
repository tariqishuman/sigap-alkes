<?php

use App\Enums\CalibrationResult;
use App\Enums\DamageReportStatus;
use App\Enums\EquipmentStatus;
use App\Filament\Widgets\AssetValueByRoomWidget;
use App\Filament\Widgets\CalibrationBodyDashboardWidget;
use App\Filament\Widgets\DamagesBreakdownChart;
use App\Filament\Widgets\ExecutiveTopMetricsWidget;
use App\Filament\Widgets\HeadNurseDashboardWidget;
use App\Filament\Widgets\InternDashboardWidget;
use App\Filament\Widgets\MonthlyUptimeChart;
use App\Filament\Widgets\TechnicianDashboardWidget;
use App\Filament\Widgets\UpcomingCalibrationsWidget;
use App\Models\Calibration;
use App\Models\DamageReport;
use App\Models\Equipment;
use App\Models\EquipmentType;
use App\Models\Repair;
use App\Models\Room;
use App\Models\TestingInstitution;
use App\Models\User;
use App\Services\DashboardMetricsService;
use App\Services\SettingsService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('renders delta chips with semantic polarity colors per design.md §3.3 and hides flat/empty deltas', function () {
    // Good when up (e.g. compliance): up = green, down = red
    $compUp = Blade::render('<x-sigap.delta value="2,1%" direction="up" polarity="good_up" />');
    $compDown = Blade::render('<x-sigap.delta value="1,5%" direction="down" polarity="good_up" />');
    expect($compUp)->toContain('#E7F5EE')->toContain('#22774F')->toContain('▲')
        ->and($compDown)->toContain('#FBEAEA')->toContain('#B83C38')->toContain('▼');

    // Good when down (e.g. overdue, repair duration): down = green, up = red
    $overDown = Blade::render('<x-sigap.delta value="2 alat" direction="down" polarity="good_down" />');
    $overUp = Blade::render('<x-sigap.delta value="2 alat" direction="up" polarity="good_down" />');
    expect($overDown)->toContain('#E7F5EE')->toContain('#22774F')->toContain('▼')
        ->and($overUp)->toContain('#FBEAEA')->toContain('#B83C38')->toContain('▲');

    // Neutral: gray
    $neutral = Blade::render('<x-sigap.delta value="3 unit" direction="up" polarity="neutral" />');
    expect($neutral)->toContain('#EFF2F2')->toContain('#55666A');

    // Flat or empty: hidden
    expect(trim(Blade::render('<x-sigap.delta value="0%" direction="flat" polarity="good_up" />')))->toBe('')
        ->and(trim(Blade::render('<x-sigap.delta :value="null" direction="up" polarity="good_up" />')))->toBe('');
});

it('computes accurate dashboard KPIs, repair duration, damages breakdown, and book value from test data', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    Cache::flush();

    $room = Room::create(['name' => 'Ruang ICU Uji', 'building' => 'Gedung A', 'floor' => '2']);
    $type = EquipmentType::create(['name' => 'Ventilator Uji', 'requires_calibration' => true, 'calibration_interval_months' => 12, 'useful_life_years' => 5]);
    $inst = TestingInstitution::create(['name' => 'BPFK Uji', 'kind' => 'bpafk', 'accreditation_number' => 'KAN-UJI-1']);
    $tech = User::where('email', 'teknisi@sigap-alkes.test')->firstOrFail();

    // Equipment 1: FitForUse, valid calibration
    $eq1 = Equipment::create([
        'inventory_code' => 'TST-001',
        'name' => 'Ventilator A',
        'equipment_type_id' => $type->id,
        'room_id' => $room->id,
        'is_active' => true,
        'acquisition_date' => now()->subMonths(12)->toDateString(),
        'acquisition_cost' => 100_000_000,
        'residual_value' => 10_000_000,
    ]);
    $eq1->forceFill(['status' => EquipmentStatus::FitForUse])->saveQuietly();

    Calibration::create([
        'equipment_id' => $eq1->id,
        'testing_institution_id' => $inst->id,
        'tested_at' => now()->subMonths(2)->toDateString(),
        'valid_until' => now()->addDays(15)->toDateString(),
        'result' => CalibrationResult::Pass,
        'certificate_number' => 'CERT-TST-1',
    ]);

    // Equipment 2: Overdue (5 days past)
    $eq2 = Equipment::create([
        'inventory_code' => 'TST-002',
        'name' => 'Ventilator B',
        'equipment_type_id' => $type->id,
        'room_id' => $room->id,
        'is_active' => true,
        'acquisition_date' => now()->subMonths(12)->toDateString(),
        'acquisition_cost' => 50_000_000,
        'residual_value' => 5_000_000,
    ]);
    $eq2->forceFill(['status' => EquipmentStatus::Overdue])->saveQuietly();

    Calibration::create([
        'equipment_id' => $eq2->id,
        'testing_institution_id' => $inst->id,
        'tested_at' => now()->subMonths(13)->toDateString(),
        'valid_until' => now()->subDays(5)->toDateString(),
        'result' => CalibrationResult::Pass,
        'certificate_number' => 'CERT-TST-2',
    ]);

    // Damage report + finished repair taking exactly 2 days (48 hours)
    $dr = DamageReport::create([
        'equipment_id' => $eq1->id,
        'reported_by' => $tech->id,
        'reported_at' => now()->subDays(3),
        'complaint' => 'Sensor oksigen error',
        'status' => DamageReportStatus::Resolved,
    ]);

    Repair::create([
        'damage_report_id' => $dr->id,
        'technician_id' => $tech->id,
        'started_at' => now()->subDays(3)->addHours(2),
        'finished_at' => now()->subDays(1),
        'action' => 'Ganti sensor oksigen',
        'labor_cost' => 250_000,
    ]);

    // Open damage report
    DamageReport::create([
        'equipment_id' => $eq2->id,
        'reported_by' => $tech->id,
        'reported_at' => now()->subHours(4),
        'complaint' => 'Alarm tidak bunyi',
        'status' => DamageReportStatus::Open,
    ]);

    app(SettingsService::class)->set('asset.uptime_target_percent', 96);

    $service = app(DashboardMetricsService::class);
    $top = $service->executiveTopMetrics('monthly', now()->toDateString(), $room->id);
    $damages = $service->damagesBreakdown('monthly', now()->toDateString(), $room->id, 'type');
    $book = $service->bookValueByRoom($room->id);

    expect($top['total_equipment']['count'])->toBe(2)
        ->and($top['total_equipment']['status_counts'][EquipmentStatus::FitForUse->value])->toBe(1)
        ->and($top['total_equipment']['status_counts'][EquipmentStatus::Overdue->value])->toBe(1)
        ->and($top['compliance']['required_count'])->toBe(2)
        ->and($top['compliance']['compliant_count'])->toBe(1)
        ->and($top['compliance']['percent'])->toBe(50.0)
        ->and($top['uptime']['target_percent'])->toBe(96)
        ->and($top['repair_duration']['avg_days'])->toBe(2.0)
        ->and($top['repair_duration']['finished_count'])->toBe(1)
        ->and($top['overdue']['count'])->toBe(1)
        ->and($top['overdue']['lte_7'])->toBe(1)
        ->and($damages['open_reports_count'])->toBe(1)
        ->and($damages['items'][0]['resolved'])->toBe(1)
        ->and($damages['items'][0]['open'])->toBe(1)
        ->and($book['total'])->toBeGreaterThan(0);
});

it('shows each role only its designated dashboard widgets and hides all cost/asset figures from restricted roles', function (string $email, array $expectedVisible, bool $forbidCost) {
    $this->seed(RoleAndPermissionSeeder::class);
    Cache::flush();

    $user = User::where('email', $email)->firstOrFail();

    $this->flushSession();
    $this->actingAs($user);

    $allWidgets = [
        ExecutiveTopMetricsWidget::class,
        DamagesBreakdownChart::class,
        UpcomingCalibrationsWidget::class,
        MonthlyUptimeChart::class,
        AssetValueByRoomWidget::class,
        TechnicianDashboardWidget::class,
        HeadNurseDashboardWidget::class,
        CalibrationBodyDashboardWidget::class,
        InternDashboardWidget::class,
    ];

    foreach ($allWidgets as $widgetClass) {
        $shouldSee = in_array($widgetClass, $expectedVisible, true);
        expect($widgetClass::canView())->toBe($shouldSee, "Widget {$widgetClass} visibility mismatch for {$email}");
    }

    $html = $this->get('/admin')->assertOk()->getContent();

    if ($forbidCost) {
        expect($html)
            ->not->toContain('Nilai Buku per Ruangan')
            ->not->toContain('estimasi, bukan pembukuan resmi');
    } else {
        expect($html)->toContain('Nilai Buku per Ruangan');
    }
})->with([
    'super_admin' => [
        'superadmin@sigap-alkes.test',
        [ExecutiveTopMetricsWidget::class, DamagesBreakdownChart::class, UpcomingCalibrationsWidget::class, MonthlyUptimeChart::class, AssetValueByRoomWidget::class],
        false,
    ],
    'admin_ipsrs' => [
        'admin@sigap-alkes.test',
        [ExecutiveTopMetricsWidget::class, DamagesBreakdownChart::class, UpcomingCalibrationsWidget::class, MonthlyUptimeChart::class, AssetValueByRoomWidget::class],
        false,
    ],
    'manajemen' => [
        'manajemen@sigap-alkes.test',
        [ExecutiveTopMetricsWidget::class, DamagesBreakdownChart::class, UpcomingCalibrationsWidget::class, MonthlyUptimeChart::class, AssetValueByRoomWidget::class],
        false,
    ],
    'teknisi' => [
        'teknisi@sigap-alkes.test',
        [TechnicianDashboardWidget::class],
        true,
    ],
    'kepala_ruangan' => [
        'kepalaruangan@sigap-alkes.test',
        [HeadNurseDashboardWidget::class],
        true,
    ],
    'badan_kalibrasi' => [
        'kalibrasi@sigap-alkes.test',
        [CalibrationBodyDashboardWidget::class],
        true,
    ],
    'magang' => [
        'magang@sigap-alkes.test',
        [InternDashboardWidget::class],
        true,
    ],
]);

it('computes non-zero previous-month overdue count and accurate delta when equipment was already overdue last month', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    Cache::flush();

    $room = Room::create(['name' => 'Ruang Tren Uji', 'building' => 'Gedung B', 'floor' => '1']);
    $type = EquipmentType::create(['name' => 'Infusion Pump Uji', 'requires_calibration' => true, 'calibration_interval_months' => 12, 'useful_life_years' => 5]);
    $inst = TestingInstitution::create(['name' => 'BPFK Tren', 'kind' => 'bpafk', 'accreditation_number' => 'KAN-TREN-1']);

    $lastMonthEnd = now()->startOfMonth()->subMonthNoOverflow()->endOfMonth();

    // Eq 1: expired 10 days BEFORE last month end -> overdue both last month AND this month
    $eq1 = Equipment::create([
        'inventory_code' => 'TRN-001',
        'name' => 'Pump Lama Terlambat',
        'equipment_type_id' => $type->id,
        'room_id' => $room->id,
        'is_active' => true,
        'acquisition_date' => now()->subYears(2)->toDateString(),
        'acquisition_cost' => 10_000_000,
        'residual_value' => 1_000_000,
    ]);
    $eq1->forceFill(['status' => EquipmentStatus::Overdue, 'created_at' => now()->subYear()])->saveQuietly();
    Calibration::create([
        'equipment_id' => $eq1->id,
        'testing_institution_id' => $inst->id,
        'tested_at' => $lastMonthEnd->copy()->subYear()->subDays(10)->toDateString(),
        'valid_until' => $lastMonthEnd->copy()->subDays(10)->toDateString(),
        'result' => CalibrationResult::Pass,
        'certificate_number' => 'CERT-TRN-1',
    ]);

    // Eq 2: expired 2 days ago (in current month) -> valid at last month end, overdue this month
    $eq2 = Equipment::create([
        'inventory_code' => 'TRN-002',
        'name' => 'Pump Baru Terlambat',
        'equipment_type_id' => $type->id,
        'room_id' => $room->id,
        'is_active' => true,
        'acquisition_date' => now()->subYears(2)->toDateString(),
        'acquisition_cost' => 10_000_000,
        'residual_value' => 1_000_000,
    ]);
    $eq2->forceFill(['status' => EquipmentStatus::Overdue, 'created_at' => now()->subYear()])->saveQuietly();
    Calibration::create([
        'equipment_id' => $eq2->id,
        'testing_institution_id' => $inst->id,
        'tested_at' => now()->subYear()->subDays(2)->toDateString(),
        'valid_until' => now()->subDays(2)->toDateString(),
        'result' => CalibrationResult::Pass,
        'certificate_number' => 'CERT-TRN-2',
    ]);

    $service = app(DashboardMetricsService::class);
    $top = $service->executiveTopMetrics('monthly', now()->toDateString(), $room->id);

    $overdueSpark = $top['overdue']['sparkline'];
    $prevMonthOverdue = $overdueSpark[count($overdueSpark) - 2];

    // Last month had 1 overdue (eq1), this month has 2 overdue (eq1 + eq2) => delta = +1
    expect($prevMonthOverdue)->toBe(1)
        ->and($top['overdue']['count'])->toBe(2)
        ->and($top['overdue']['delta'])->toBe(1)
        ->and($top['overdue']['delta_label'])->toBe('1 alat');
});
