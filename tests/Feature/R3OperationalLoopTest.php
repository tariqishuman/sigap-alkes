<?php

use App\Enums\CalibrationResult;
use App\Enums\DamageReportStatus;
use App\Enums\EquipmentStatus;
use App\Enums\TestingInstitutionKind;
use App\Enums\UserRole;
use App\Models\Calibration;
use App\Models\DamageReport;
use App\Models\Equipment;
use App\Models\EquipmentType;
use App\Models\Room;
use App\Models\TestingInstitution;
use App\Models\User;
use App\Notifications\DamageReportedNotification;
use App\Notifications\UnsafeReportRateLimitNotification;
use App\Services\DamageReportService;
use App\Services\EquipmentStatusService;
use App\Services\RepairService;
use App\Services\UptimeCalculator;
use Carbon\Carbon;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
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
