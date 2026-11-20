<?php

use App\Enums\AlertTier;
use App\Enums\CalibrationResult;
use App\Enums\DamageReportStatus;
use App\Enums\EquipmentStatus;
use App\Enums\PreventiveMaintenanceResult;
use App\Enums\TestingInstitutionKind;
use App\Enums\UserRole;
use App\Filament\Resources\DamageReports\DamageReportResource;
use App\Models\Calibration;
use App\Models\DamageReport;
use App\Models\Equipment;
use App\Models\EquipmentType;
use App\Models\PreventiveMaintenance;
use App\Models\Room;
use App\Models\TestingInstitution;
use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\CriticalReportEscalationNotification;
use App\Notifications\DamageReportedNotification;
use App\Notifications\EquipmentOperationalNotification;
use App\Notifications\UnsafeReportRateLimitNotification;
use App\Services\DamageReportService;
use App\Services\EquipmentStatusService;
use App\Services\PreventiveMaintenanceService;
use App\Services\RepairService;
use App\Services\UptimeCalculator;
use Carbon\Carbon;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->icuRoom = Room::create(['code' => 'R3-ICU', 'name' => 'Ruang ICU', 'building' => 'Gedung A', 'floor' => 'Lt 2']);
    $this->igdRoom = Room::create(['code' => 'R3-IGD', 'name' => 'Ruang IGD', 'building' => 'Gedung A', 'floor' => 'Lt 1']);

    $this->typeCalibrated = EquipmentType::create([
        'name' => 'Ventilator R3',
        'requires_calibration' => true,
        'calibration_interval_months' => 12,
        'requires_installation' => false,
    ]);

    $this->institution = TestingInstitution::create([
        'name' => 'BPFK Surabaya',
        'kind' => TestingInstitutionKind::Bpafk,
    ]);

    $this->admin = User::factory()->create(['name' => 'Admin IPSRS R3']);
    $this->admin->assignRole(UserRole::AdminIpsrs->value);

    $this->teknisi = User::factory()->create(['name' => 'Teknisi R3']);
    $this->teknisi->assignRole(UserRole::Teknisi->value);

    $this->kepalaIcu = User::factory()->create(['name' => 'Kepala ICU R3', 'room_id' => $this->icuRoom->id]);
    $this->kepalaIcu->assignRole(UserRole::KepalaRuangan->value);

    $this->kepalaIgd = User::factory()->create(['name' => 'Kepala IGD R3', 'room_id' => $this->igdRoom->id]);
    $this->kepalaIgd->assignRole(UserRole::KepalaRuangan->value);

    $this->magang = User::factory()->create(['name' => 'Magang R3']);
    $this->magang->assignRole(UserRole::Magang->value);
});

afterEach(function () {
    Carbon::setTestNow();
});

function makeR3Equipment(
    EquipmentType $type,
    Room $room,
    string $code,
    EquipmentStatus $status = EquipmentStatus::FitForUse,
    ?TestingInstitution $institution = null,
    ?string $validUntil = null,
): Equipment {
    $eq = Equipment::create([
        'inventory_code' => $code,
        'name' => "Alat {$code}",
        'equipment_type_id' => $type->id,
        'room_id' => $room->id,
        'acquisition_date' => '2025-01-01',
        'acquisition_cost' => '30000000.00',
        'useful_life_years' => 5,
        'residual_value' => '3000000.00',
        'is_active' => true,
    ]);

    $eq->forceFill(['status' => $status])->saveQuietly();

    if ($institution && $validUntil) {
        Calibration::create([
            'equipment_id' => $eq->id,
            'testing_institution_id' => $institution->id,
            'tested_at' => today()->subMonth(),
            'result' => CalibrationResult::Pass,
            'certificate_number' => "CERT-{$code}",
            'valid_until' => $validUntil,
            'recorded_by' => null,
        ]);
    }

    return $eq->fresh();
}

describe('R3 Bagian 1 — Laporan kerusakan memengaruhi status & pengaman', function () {
    it('transitions FitForUse/CalibrationDue/Overdue/UnderTesting to Unfit on unsafe report and leaves UnderRepair/Unfit unchanged', function () {
        Notification::fake();
        $service = app(DamageReportService::class);

        foreach ([
            EquipmentStatus::FitForUse,
            EquipmentStatus::CalibrationDue,
            EquipmentStatus::Overdue,
            EquipmentStatus::UnderTesting,
        ] as $idx => $initialStatus) {
            $eq = makeR3Equipment($this->typeCalibrated, $this->icuRoom, "R3-UNSAFE-{$idx}", $initialStatus);
            $report = $service->report([
                'equipment_id' => $eq->id,
                'is_safe_to_use' => false,
                'complaint' => 'Alat mengeluarkan percikan listrik saat dinyalakan.',
            ], $this->teknisi);

            expect($eq->fresh()->status)->toBe(EquipmentStatus::Unfit)
                ->and($report->marked_unfit_by_report)->toBeTrue()
                ->and($report->is_safe_to_use)->toBeFalse();
        }

        // UnderRepair stays UnderRepair
        $underRepair = makeR3Equipment($this->typeCalibrated, $this->icuRoom, 'R3-UNDER-REP', EquipmentStatus::UnderRepair);
        $repReport = $service->report([
            'equipment_id' => $underRepair->id,
            'is_safe_to_use' => false,
            'complaint' => 'Ditemukan kebocoran tambahan pada selang oksigen.',
        ], $this->admin);

        expect($underRepair->fresh()->status)->toBe(EquipmentStatus::UnderRepair)
            ->and($repReport->marked_unfit_by_report)->toBeFalse();

        // Safe report leaves status unchanged
        $safeEq = makeR3Equipment($this->typeCalibrated, $this->icuRoom, 'R3-SAFE-1', EquipmentStatus::FitForUse);
        $safeReport = $service->report([
            'equipment_id' => $safeEq->id,
            'is_safe_to_use' => true,
            'complaint' => 'Layar agak redup tetapi pembacaan tetap akurat.',
        ], $this->kepalaIcu);

        expect($safeEq->fresh()->status)->toBe(EquipmentStatus::FitForUse)
            ->and($safeReport->marked_unfit_by_report)->toBeFalse();
    });

    it('closes report without repair and restores equipment status via calibration evaluation', function () {
        Notification::fake();
        $service = app(DamageReportService::class);

        $eq = makeR3Equipment(
            $this->typeCalibrated,
            $this->icuRoom,
            'R3-CLOSE-01',
            EquipmentStatus::FitForUse,
            $this->institution,
            today()->addMonths(6)->toDateString(),
        );

        $report = $service->report([
            'equipment_id' => $eq->id,
            'is_safe_to_use' => false,
            'complaint' => 'Alarm apnea berbunyi terus menerus tanpa henti.',
        ], $this->kepalaIcu);

        expect($eq->fresh()->status)->toBe(EquipmentStatus::Unfit);

        $closed = $service->closeWithoutRepair($report, 'Alarm palsu akibat pemasangan sensor tidak rapat.', $this->teknisi);

        expect($closed->status)->toBe(DamageReportStatus::Resolved)
            ->and($closed->closed_without_repair_reason)->toContain('Alarm palsu')
            ->and($eq->fresh()->status)->toBe(EquipmentStatus::FitForUse);

        // With expired certificate -> restores to Overdue
        $eqExpired = makeR3Equipment(
            $this->typeCalibrated,
            $this->icuRoom,
            'R3-CLOSE-02',
            EquipmentStatus::Overdue,
            $this->institution,
            today()->subDays(10)->toDateString(),
        );
        $report2 = $service->report([
            'equipment_id' => $eqExpired->id,
            'is_safe_to_use' => false,
            'complaint' => 'Tombol pengaturan tekanan tampak tidak merespons.',
        ], $this->teknisi);

        expect($eqExpired->fresh()->status)->toBe(EquipmentStatus::Unfit);

        $service->closeWithoutRepair($report2, 'Panel dalam mode kunci (child lock), bukan rusak.', $this->admin);

        expect($eqExpired->fresh()->status)->toBe(EquipmentStatus::Overdue);
    });

    it('counts downtime in UptimeCalculator starting from unsafe damage report timestamp', function () {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-11-01 00:00:00'));

        $eq = makeR3Equipment(
            $this->typeCalibrated,
            $this->icuRoom,
            'R3-UPTIME-01',
            EquipmentStatus::FitForUse,
            $this->institution,
            '2027-06-01',
        );
        app(EquipmentStatusService::class)->initializeStatus($eq, $this->admin->id);
        // Set status history to FitForUse at start of month
        $eq->forceFill(['status' => EquipmentStatus::FitForUse])->saveQuietly();
        $eq->statusHistories()->delete();
        $eq->statusHistories()->create([
            'from_status' => null,
            'to_status' => EquipmentStatus::FitForUse,
            'changed_at' => Carbon::parse('2026-11-01 00:00:00'),
            'reason' => 'Awal bulan',
        ]);

        // Day 2 00:00 -> reported unsafe (Unfit)
        Carbon::setTestNow(Carbon::parse('2026-11-02 00:00:00'));
        $report = app(DamageReportService::class)->report([
            'equipment_id' => $eq->id,
            'is_safe_to_use' => false,
            'complaint' => 'Alat mati total saat dinyalakan di ruang ICU.',
        ], $this->kepalaIcu);

        // Day 3 00:00 -> repair starts (24h already downtime as Unfit)
        Carbon::setTestNow(Carbon::parse('2026-11-03 00:00:00'));
        $repair = app(RepairService::class)->start([
            'damage_report_id' => $report->id,
            'started_at' => now(),
            'action' => 'Cek power supply',
        ], $this->teknisi->id);

        // Day 4 00:00 -> repair finishes (another 24h downtime as UnderRepair = total 48h downtime)
        Carbon::setTestNow(Carbon::parse('2026-11-04 00:00:00'));
        app(RepairService::class)->finish($repair, [
            'finished_at' => now(),
            'action' => 'Ganti sekring dan uji fungsi',
        ], $this->teknisi->id);

        // Evaluate 4-day window [Nov 1 00:00, Nov 5 00:00] = 96 hours, 48h downtime => 50.0% uptime
        Carbon::setTestNow(Carbon::parse('2026-11-05 00:00:00'));
        $metrics = app(UptimeCalculator::class)->forEquipment(
            $eq->fresh('statusHistories'),
            Carbon::parse('2026-11-01 00:00:00'),
            Carbon::parse('2026-11-05 00:00:00'),
            Carbon::parse('2026-11-05 00:00:00'),
        );

        expect($metrics['downtime_hours'])->toBe(48.0)
            ->and($metrics['uptime_percent'])->toBe(50.0);
    });

    it('puts magang unsafe report into pending_verification without changing equipment status until verified', function () {
        Notification::fake();
        $service = app(DamageReportService::class);
        $eq = makeR3Equipment($this->typeCalibrated, $this->icuRoom, 'R3-MAGANG-01', EquipmentStatus::FitForUse);

        $report = $service->report([
            'equipment_id' => $eq->id,
            'is_safe_to_use' => false,
            'complaint' => 'Layar berkedip keras dan suhu terasa sangat panas.',
        ], $this->magang);

        expect($report->status)->toBe(DamageReportStatus::PendingVerification)
            ->and($eq->fresh()->status)->toBe(EquipmentStatus::FitForUse);

        // Verify confirm -> moves to Open and equipment becomes Unfit
        $verified = $service->verifyUnsafeReport($report, true, $this->teknisi);

        expect($verified->status)->toBe(DamageReportStatus::Open)
            ->and($verified->verified_by)->toBe($this->teknisi->id)
            ->and($eq->fresh()->status)->toBe(EquipmentStatus::Unfit);

        // Another magang report rejected -> status stays FitForUse
        $eq2 = makeR3Equipment($this->typeCalibrated, $this->icuRoom, 'R3-MAGANG-02', EquipmentStatus::FitForUse);
        $report2 = $service->report([
            'equipment_id' => $eq2->id,
            'is_safe_to_use' => false,
            'complaint' => 'Alat tidak menyala saat tombol ditekan lama.',
        ], $this->magang);

        $rejected = $service->verifyUnsafeReport($report2, false, $this->admin, 'Kabel daya belum dicolokkan.');

        expect($rejected->status)->toBe(DamageReportStatus::Rejected)
            ->and($rejected->rejected_reason)->toBe('Kabel daya belum dicolokkan.')
            ->and($eq2->fresh()->status)->toBe(EquipmentStatus::FitForUse);
    });

    it('merges duplicate reports on active equipment into damage_report_notes and only notifies on safe-to-unsafe escalation', function () {
        Notification::fake();
        $service = app(DamageReportService::class);
        $eq = makeR3Equipment($this->typeCalibrated, $this->icuRoom, 'R3-DUP-01', EquipmentStatus::FitForUse);

        $first = $service->report([
            'equipment_id' => $eq->id,
            'is_safe_to_use' => true,
            'complaint' => 'Tombol volume alarm agak keras ditekan.',
        ], $this->kepalaIcu);

        Notification::assertSentToTimes($this->teknisi, DamageReportedNotification::class, 1);

        // Second safe report -> merged as note, NO new notification
        $second = $service->report([
            'equipment_id' => $eq->id,
            'is_safe_to_use' => true,
            'complaint' => 'Suara alarm kadang mengecil saat dipakai.',
        ], $this->teknisi);

        expect($second->id)->toBe($first->id)
            ->and($second->wasMergedIntoActiveReport)->toBeTrue()
            ->and(DamageReport::where('equipment_id', $eq->id)->count())->toBe(1)
            ->and($first->notes()->count())->toBe(1);

        Notification::assertSentToTimes($this->teknisi, DamageReportedNotification::class, 1);

        // Third report states UNSAFE while initial was SAFE -> escalates status & sends notification
        $third = $service->report([
            'equipment_id' => $eq->id,
            'is_safe_to_use' => false,
            'complaint' => 'Sekarang alarm mati total dan tidak berbunyi.',
        ], $this->kepalaIcu);

        expect($third->id)->toBe($first->id)
            ->and($third->is_safe_to_use)->toBeFalse()
            ->and($eq->fresh()->status)->toBe(EquipmentStatus::Unfit)
            ->and($first->notes()->count())->toBe(2);

        Notification::assertSentToTimes($this->teknisi, DamageReportedNotification::class, 2);
    });

    it('enforces max 5 unsafe reports per user per hour and notifies admin_ipsrs when exceeded', function () {
        Notification::fake();
        $service = app(DamageReportService::class);

        for ($i = 1; $i <= 5; $i++) {
            $eq = makeR3Equipment($this->typeCalibrated, $this->icuRoom, "R3-RATE-0{$i}", EquipmentStatus::FitForUse);
            $service->report([
                'equipment_id' => $eq->id,
                'is_safe_to_use' => false,
                'complaint' => "Kerusakan tidak aman ke-{$i} pada alat medis.",
            ], $this->kepalaIcu);
        }

        $eq6 = makeR3Equipment($this->typeCalibrated, $this->icuRoom, 'R3-RATE-06', EquipmentStatus::FitForUse);

        expect(fn () => $service->report([
            'equipment_id' => $eq6->id,
            'is_safe_to_use' => false,
            'complaint' => 'Kerusakan tidak aman ke-6 melebihi batas per jam.',
        ], $this->kepalaIcu))->toThrow(ValidationException::class);

        Notification::assertSentTo($this->admin, UnsafeReportRateLimitNotification::class);
    });

    it('protects private damage report photos from unauthenticated access and unauthorized room heads', function () {
        Storage::fake('local');
        Storage::disk('local')->put('damage-report-photos/test-photo.jpg', 'fake-jpeg-bytes');

        $eq = makeR3Equipment($this->typeCalibrated, $this->icuRoom, 'R3-PHOTO-01', EquipmentStatus::FitForUse);
        $report = app(DamageReportService::class)->report([
            'equipment_id' => $eq->id,
            'is_safe_to_use' => true,
            'complaint' => 'Terdapat retakan kecil di casing bagian belakang.',
            'photos' => ['damage-report-photos/test-photo.jpg'],
        ], $this->kepalaIcu);

        $url = route('damage-reports.photo', ['damageReport' => $report->id, 'index' => 0]);

        // Unauthenticated -> redirected to login
        $this->get($url)->assertRedirect();

        // Kepala ruangan of another room -> 403
        $this->actingAs($this->kepalaIgd)->get($url)->assertForbidden();

        // Kepala ruangan of same room -> 200
        $this->actingAs($this->kepalaIcu)->get($url)->assertOk();

        // Teknisi -> 200
        $this->actingAs($this->teknisi)->get($url)->assertOk();
    });
});

describe('R3 Bagian 2 — Lapor kerusakan dari halaman QR publik', function () {
    it('shows Laporkan kerusakan button on public QR page and redirects guest to login with intended URL', function () {
        $eq = makeR3Equipment($this->typeCalibrated, $this->icuRoom, 'R3-QR-01', EquipmentStatus::FitForUse);

        $publicUrl = route('public.equipment.show', ['qr_token' => $eq->qr_token]);
        $reportUrl = route('public.equipment.report', ['qr_token' => $eq->qr_token]);

        $this->get($publicUrl)
            ->assertOk()
            ->assertSee('Laporkan kerusakan')
            ->assertSee($reportUrl, false)
            ->assertDontSee('30.000.000');

        // Guest clicking report button -> redirected to login with intended URL
        $response = $this->get($reportUrl);
        $response->assertRedirect('/admin/login');
        expect(session('url.intended'))->toBe($reportUrl);

        // Once logged in as kepala_ruangan of same room -> redirected to prefilled create form
        $this->actingAs($this->kepalaIcu)
            ->get($reportUrl)
            ->assertRedirect(DamageReportResource::getUrl('create', ['equipment_id' => $eq->id]));
    });

    it('shows clear Indonesian message when user lacks permission to report equipment from QR', function () {
        $eq = makeR3Equipment($this->typeCalibrated, $this->icuRoom, 'R3-QR-02', EquipmentStatus::FitForUse);
        $reportUrl = route('public.equipment.report', ['qr_token' => $eq->qr_token]);

        // Kepala ruangan IGD trying to report ICU equipment
        $this->actingAs($this->kepalaIgd)
            ->get($reportUrl)
            ->assertForbidden()
            ->assertSee('Tidak dapat melaporkan kerusakan untuk alat ini')
            ->assertSee('ruangan Anda sendiri');

        // Manajemen trying to report
        $manajemen = User::factory()->create(['name' => 'Manajemen R3']);
        $manajemen->assignRole(UserRole::Manajemen->value);

        $this->actingAs($manajemen)
            ->get($reportUrl)
            ->assertForbidden()
            ->assertSee('tidak memiliki izin untuk membuat laporan kerusakan');
    });
});

describe('R3 Bagian 3 — Notifikasi untuk kepala ruangan', function () {
    it('notifies kepala_ruangan ONLY for equipment in their own room across status & repair events without cost data', function () {
        Notification::fake();
        $statusService = app(EquipmentStatusService::class);
        $damageService = app(DamageReportService::class);
        $repairService = app(RepairService::class);

        $icuEq = makeR3Equipment(
            $this->typeCalibrated,
            $this->icuRoom,
            'R3-NOTIF-ICU',
            EquipmentStatus::CalibrationDue,
            $this->institution,
            today()->subDay()->toDateString(),
        );

        // 1. Overdue
        $statusService->evaluate($icuEq);
        Notification::assertSentTo(
            $this->kepalaIcu,
            EquipmentOperationalNotification::class,
            fn ($n) => $n->event === EquipmentOperationalNotification::EVENT_OVERDUE
                && ! str_contains($n->body(), 'Rp'),
        );
        Notification::assertNotSentTo($this->kepalaIgd, EquipmentOperationalNotification::class);

        // 2. Unfit (via unsafe report by teknisi)
        $report = $damageService->report([
            'equipment_id' => $icuEq->id,
            'is_safe_to_use' => false,
            'complaint' => 'Sensor aliran udara tidak terbaca di monitor.',
        ], $this->teknisi);

        Notification::assertSentTo(
            $this->kepalaIcu,
            EquipmentOperationalNotification::class,
            fn ($n) => $n->event === EquipmentOperationalNotification::EVENT_UNFIT,
        );
        Notification::assertNotSentTo($this->kepalaIgd, EquipmentOperationalNotification::class);

        // 3. Repair started
        $repair = $repairService->start([
            'damage_report_id' => $report->id,
            'started_at' => now(),
            'labor_cost' => '750000',
            'action' => 'Penggantian sensor aliran udara',
        ], $this->teknisi->id);

        Notification::assertSentTo(
            $this->kepalaIcu,
            EquipmentOperationalNotification::class,
            fn ($n) => $n->event === EquipmentOperationalNotification::EVENT_REPAIR_STARTED
                && ! str_contains($n->body(), '750'),
        );

        // 4. Repair finished
        $repairService->finish($repair, [
            'finished_at' => now()->addHour(),
            'action' => 'Sensor diganti dan uji fungsi normal',
        ], $this->teknisi->id);

        Notification::assertSentTo(
            $this->kepalaIcu,
            EquipmentOperationalNotification::class,
            fn ($n) => $n->event === EquipmentOperationalNotification::EVENT_REPAIR_FINISHED
                && ! str_contains($n->body(), '750'),
        );

        // 5. Close without repair
        $icuEq2 = makeR3Equipment($this->typeCalibrated, $this->icuRoom, 'R3-NOTIF-ICU-2', EquipmentStatus::FitForUse);
        $report2 = $damageService->report([
            'equipment_id' => $icuEq2->id,
            'is_safe_to_use' => false,
            'complaint' => 'Alarm baterai rendah muncul terus menerus.',
        ], $this->teknisi);
        $damageService->closeWithoutRepair($report2, 'Kabel adaptor hanya longgar.', $this->admin);

        Notification::assertSentTo(
            $this->kepalaIcu,
            EquipmentOperationalNotification::class,
            fn ($n) => $n->event === EquipmentOperationalNotification::EVENT_CLOSED_WITHOUT_REPAIR,
        );
        Notification::assertNotSentTo($this->kepalaIgd, EquipmentOperationalNotification::class);
    });
});

describe('R3 Bagian 4 — Pemeliharaan preventif (PM)', function () {
    it('computes next_due_at from pm_interval_months, flags needs_followup on unhealthy items without changing equipment status', function () {
        $pmType = EquipmentType::create([
            'name' => 'Infusion Pump PM',
            'requires_calibration' => true,
            'calibration_interval_months' => 12,
            'pm_interval_months' => 3,
            'pm_checklist' => ['Kebersihan & kondisi fisik', 'Kabel & konektor', 'Baterai', 'Alarm berfungsi'],
            'requires_installation' => false,
        ]);

        $eq = makeR3Equipment($pmType, $this->icuRoom, 'R3-PM-01', EquipmentStatus::FitForUse);
        $service = app(PreventiveMaintenanceService::class);

        $pm = $service->record([
            'equipment_id' => $eq->id,
            'performed_at' => '2026-08-15',
            'checklist_results' => [
                ['item' => 'Kebersihan & kondisi fisik', 'status' => 'good', 'note' => null],
                ['item' => 'Baterai', 'status' => 'needs_attention', 'note' => 'Baterai cepat drop'],
            ],
            'notes' => 'Perlu penggantian baterai segera.',
        ], $this->teknisi);

        expect($pm->next_due_at->toDateString())->toBe('2026-11-15')
            ->and($pm->result)->toBe(PreventiveMaintenanceResult::NeedsFollowup)
            ->and($pm->hasUnhealthyItems())->toBeTrue()
            ->and($pm->unhealthyItemsSummary())->toContain('Baterai (Baterai cepat drop)')
            ->and($eq->fresh()->status)->toBe(EquipmentStatus::FitForUse);
    });

    it('enforces role-based PM access (teknisi/admin record, kepala_ruangan room-scoped view, magang/manajemen view, badan_kalibrasi denied)', function () {
        $pmType = EquipmentType::create([
            'name' => 'Monitor PM',
            'requires_calibration' => true,
            'calibration_interval_months' => 12,
            'pm_interval_months' => 6,
            'pm_checklist' => ['Kebersihan', 'Kabel', 'Alarm'],
            'requires_installation' => false,
        ]);

        $icuEq = makeR3Equipment($pmType, $this->icuRoom, 'R3-PM-ICU', EquipmentStatus::FitForUse);
        $igdEq = makeR3Equipment($pmType, $this->igdRoom, 'R3-PM-IGD', EquipmentStatus::FitForUse);

        $pmService = app(PreventiveMaintenanceService::class);
        $icuPm = $pmService->record([
            'equipment_id' => $icuEq->id,
            'performed_at' => today()->toDateString(),
            'checklist_results' => [['item' => 'Kebersihan', 'status' => 'good', 'note' => null]],
        ], $this->teknisi);
        $igdPm = $pmService->record([
            'equipment_id' => $igdEq->id,
            'performed_at' => today()->toDateString(),
            'checklist_results' => [['item' => 'Kebersihan', 'status' => 'good', 'note' => null]],
        ], $this->admin);

        $manajemen = User::factory()->create(['name' => 'Manajemen PM']);
        $manajemen->assignRole(UserRole::Manajemen->value);

        $badan = User::factory()->create(['name' => 'Badan PM', 'testing_institution_id' => $this->institution->id]);
        $badan->assignRole(UserRole::BadanKalibrasi->value);

        expect($this->teknisi->can('create', PreventiveMaintenance::class))->toBeTrue()
            ->and($this->admin->can('create', PreventiveMaintenance::class))->toBeTrue()
            ->and($this->kepalaIcu->can('view', $icuPm))->toBeTrue()
            ->and($this->kepalaIcu->can('view', $igdPm))->toBeFalse()
            ->and($this->kepalaIcu->can('create', PreventiveMaintenance::class))->toBeFalse()
            ->and($this->magang->can('view', $icuPm))->toBeTrue()
            ->and($this->magang->can('create', PreventiveMaintenance::class))->toBeFalse()
            ->and($manajemen->can('view', $icuPm))->toBeTrue()
            ->and($badan->can('viewAny', PreventiveMaintenance::class))->toBeFalse()
            ->and($this->admin->can('delete', $icuPm))->toBeFalse();
    });
});

describe('R3 Bagian 5 — Sertifikat kalibrasi (PDF)', function () {
    it('serves calibration PDF certificate only to authorized users and keeps it off public QR page', function () {
        Storage::fake('local');
        Storage::disk('local')->put('calibration-certificates/cert-r3.pdf', '%PDF-1.4 fake pdf content');

        $otherInstitution = TestingInstitution::create([
            'name' => 'Lab Swasta Lain',
            'kind' => TestingInstitutionKind::Swasta,
        ]);

        $eq = makeR3Equipment($this->typeCalibrated, $this->icuRoom, 'R3-CERT-01', EquipmentStatus::FitForUse);
        $calibration = Calibration::create([
            'equipment_id' => $eq->id,
            'testing_institution_id' => $this->institution->id,
            'tested_at' => today()->subWeek(),
            'result' => CalibrationResult::Pass,
            'certificate_number' => 'CERT-R3-001',
            'valid_until' => today()->addMonths(12),
            'certificate_path' => 'calibration-certificates/cert-r3.pdf',
            'recorded_by' => $this->teknisi->id,
        ]);

        $certUrl = route('calibrations.certificate', $calibration);

        // Public QR page does NOT link certificate file
        $this->get(route('public.equipment.show', ['qr_token' => $eq->qr_token]))
            ->assertOk()
            ->assertDontSee($certUrl)
            ->assertDontSee('cert-r3.pdf');

        // Unauthenticated cannot download
        $this->get($certUrl)->assertRedirect();

        // Teknisi & Kepala Ruangan ICU can view
        $this->actingAs($this->teknisi)->get($certUrl)->assertOk();
        $this->actingAs($this->kepalaIcu)->get($certUrl)->assertOk();

        // Kepala Ruangan IGD (different room) cannot view
        $this->actingAs($this->kepalaIgd)->get($certUrl)->assertForbidden();

        // Badan kalibrasi of same institution can view & update; other institution cannot
        $badanSame = User::factory()->create(['testing_institution_id' => $this->institution->id]);
        $badanSame->assignRole(UserRole::BadanKalibrasi->value);

        $badanOther = User::factory()->create(['testing_institution_id' => $otherInstitution->id]);
        $badanOther->assignRole(UserRole::BadanKalibrasi->value);

        $this->actingAs($badanSame)->get($certUrl)->assertOk();
        $this->actingAs($badanOther)->get($certUrl)->assertForbidden();
        expect($badanSame->can('update', $calibration))->toBeTrue()
            ->and($badanOther->can('update', $calibration))->toBeFalse();
    });
});

describe('R3 Bagian 6 — Notifikasi push, alert tiers, Saya tangani, dan eskalasi', function () {
    it('routes push channel by alert tier (critical vs normal equipment, user prefs, no push to magang, no cost in body)', function () {
        Notification::fake();

        $criticalType = EquipmentType::create([
            'name' => 'Defibrilator Kritis',
            'requires_calibration' => true,
            'calibration_interval_months' => 12,
            'requires_installation' => false,
            'is_critical' => true,
        ]);

        $normalRoom = Room::create(['code' => 'R3-POLI', 'name' => 'Poli Umum', 'building' => 'Gedung B', 'floor' => 'Lt 1', 'is_critical_area' => false]);
        $normalType = EquipmentType::create([
            'name' => 'Timbangan Digital',
            'requires_calibration' => false,
            'calibration_interval_months' => 12,
            'requires_installation' => false,
            'is_critical' => false,
        ]);

        $criticalEq = makeR3Equipment($criticalType, $this->icuRoom, 'R3-CRIT-01', EquipmentStatus::FitForUse);
        $normalEq = makeR3Equipment($normalType, $normalRoom, 'R3-NORM-01', EquipmentStatus::FitForUse);

        // Teknisi disables Important push, keeps Critical always on
        $this->teknisi->update(['push_pref_important' => false, 'push_pref_info' => false]);

        $service = app(DamageReportService::class);

        // 1. Unsafe report on CRITICAL equipment -> Critical tier -> push ALWAYS active for teknisi
        $service->report([
            'equipment_id' => $criticalEq->id,
            'is_safe_to_use' => false,
            'complaint' => 'Paddle defibrilator tidak mengeluarkan energi kejutan.',
        ], $this->kepalaIcu);

        Notification::assertSentTo(
            $this->teknisi,
            DamageReportedNotification::class,
            fn ($n, $channels) => $n->alertTier() === AlertTier::Critical
                && in_array(WebPushChannel::class, $channels, true)
                && ($n->toWebPush($this->teknisi)['requireInteraction'] ?? false) === true
                && ! str_contains($n->body(), 'Rp'),
        );

        // 2. Unsafe report on NORMAL equipment -> Important tier -> teknisi opted out of Important push, admin kept it on
        $kepalaPoli = User::factory()->create(['name' => 'Kepala Poli', 'room_id' => $normalRoom->id]);
        $kepalaPoli->assignRole(UserRole::KepalaRuangan->value);

        $service->report([
            'equipment_id' => $normalEq->id,
            'is_safe_to_use' => false,
            'complaint' => 'Layar timbangan berkedip dan tidak stabil saat ditimbang.',
        ], $kepalaPoli);

        Notification::assertSentTo(
            $this->teknisi,
            DamageReportedNotification::class,
            fn ($n, $channels) => $n->damageReport->equipment_id === $normalEq->id
                && $n->alertTier() === AlertTier::Important
                && ! in_array(WebPushChannel::class, $channels, true),
        );
        Notification::assertSentTo(
            $this->admin,
            DamageReportedNotification::class,
            fn ($n, $channels) => $n->damageReport->equipment_id === $normalEq->id
                && in_array(WebPushChannel::class, $channels, true),
        );

        // 3. Magang NEVER receives WebPush even for Critical
        expect($this->magang->shouldReceivePushForTier(AlertTier::Critical))->toBeFalse()
            ->and($this->magang->shouldReceivePushForTier(AlertTier::Important))->toBeFalse();
    });

    it('records Saya tangani atomically only once for the first responder', function () {
        Notification::fake();
        $teknisi2 = User::factory()->create(['name' => 'Teknisi Kedua']);
        $teknisi2->assignRole(UserRole::Teknisi->value);

        $eq = makeR3Equipment($this->typeCalibrated, $this->icuRoom, 'R3-ACK-01', EquipmentStatus::FitForUse);
        $service = app(DamageReportService::class);
        $report = $service->report([
            'equipment_id' => $eq->id,
            'is_safe_to_use' => false,
            'complaint' => 'Alarm tekanan tinggi menyala terus menerus.',
        ], $this->kepalaIcu);

        $first = $service->acknowledge($report, $this->teknisi);
        $second = $service->acknowledge($report, $teknisi2);

        expect($first['acknowledged'])->toBeTrue()
            ->and($second['acknowledged'])->toBeFalse()
            ->and($report->fresh()->acknowledged_by)->toBe($this->teknisi->id);
    });

    it('requires a valid, unexpired, user-bound signed URL and POST confirmation for Saya tangani', function () {
        Notification::fake();
        $teknisi2 = User::factory()->create(['name' => 'Teknisi Lain']);
        $teknisi2->assignRole(UserRole::Teknisi->value);

        $eq = makeR3Equipment($this->typeCalibrated, $this->icuRoom, 'R3-SIGN-01', EquipmentStatus::FitForUse);
        $report = app(DamageReportService::class)->report([
            'equipment_id' => $eq->id,
            'is_safe_to_use' => false,
            'complaint' => 'Monitor kehilangan sinyal ECG secara mendadak.',
        ], $this->kepalaIcu);

        $notification = new DamageReportedNotification($report);
        $signedConfirmUrl = $notification->ackUrl($this->teknisi);

        // 1. Unsigned URL rejected with 403
        $unsignedUrl = route('damage-reports.acknowledge.confirm', ['damageReport' => $report->id, 'user' => $this->teknisi->id]);
        $this->actingAs($this->teknisi)->get($unsignedUrl)->assertForbidden();

        // 2. Signed URL opened by another user rejected with 403
        $this->actingAs($teknisi2)->get($signedConfirmUrl)->assertForbidden();

        // 3. Valid signed URL opened by recipient shows confirmation page WITHOUT mutating DB yet
        $this->actingAs($this->teknisi)
            ->get($signedConfirmUrl)
            ->assertOk()
            ->assertSee('Tandai laporan ini sedang Anda tangani?');
        expect($report->fresh()->acknowledged_by)->toBeNull();

        // 4. Signed POST by recipient records acknowledgement; another user posting is rejected (403)
        $signedStoreUrl = URL::temporarySignedRoute(
            'damage-reports.acknowledge.store',
            now()->addMinutes(30),
            ['damageReport' => $report->id, 'user' => $this->teknisi->id],
        );
        $this->actingAs($teknisi2)->post($signedStoreUrl)->assertForbidden();

        $this->actingAs($this->teknisi)
            ->post($signedStoreUrl)
            ->assertRedirect(DamageReportResource::getUrl('index'));
        expect($report->fresh()->acknowledged_by)->toBe($this->teknisi->id);

        // 5. Expired signed URL (> 24h) rejected with 403
        Carbon::setTestNow(now()->addHours(25));
        $this->actingAs($this->teknisi)->get($signedConfirmUrl)->assertForbidden();
    });

    it('escalates unacknowledged critical reports after 15 minutes ONCE to admin_ipsrs and manajemen', function () {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-11-20 10:00:00'));

        $this->icuRoom->update(['is_critical_area' => true]);
        $manajemen = User::factory()->create(['name' => 'Direktur RS']);
        $manajemen->assignRole(UserRole::Manajemen->value);

        $eq = makeR3Equipment($this->typeCalibrated, $this->icuRoom, 'R3-ESC-01', EquipmentStatus::FitForUse);
        $report = app(DamageReportService::class)->report([
            'equipment_id' => $eq->id,
            'is_safe_to_use' => false,
            'complaint' => 'Ventilator ICU berhenti memompa oksigen mendadak.',
        ], $this->kepalaIcu);

        // 10 minutes later -> not yet escalated
        Carbon::setTestNow(Carbon::parse('2026-11-20 10:10:00'));
        $this->artisan('damage-reports:escalate-critical')->assertSuccessful();
        expect($report->fresh()->escalated_at)->toBeNull();
        Notification::assertNotSentTo($manajemen, CriticalReportEscalationNotification::class);

        // 16 minutes later -> escalated ONCE to admin_ipsrs + manajemen (with WebPush!)
        Carbon::setTestNow(Carbon::parse('2026-11-20 10:16:00'));
        $this->artisan('damage-reports:escalate-critical')->assertSuccessful();
        expect($report->fresh()->escalated_at)->not->toBeNull();

        Notification::assertSentTo(
            $manajemen,
            CriticalReportEscalationNotification::class,
            fn ($n, $channels) => in_array(WebPushChannel::class, $channels, true),
        );
        Notification::assertSentTo($this->admin, CriticalReportEscalationNotification::class);

        // Running again at 20 minutes -> NOT sent a second time
        Carbon::setTestNow(Carbon::parse('2026-11-20 10:20:00'));
        $this->artisan('damage-reports:escalate-critical')->assertSuccessful();
        Notification::assertSentToTimes($manajemen, CriticalReportEscalationNotification::class, 1);
    });
});
