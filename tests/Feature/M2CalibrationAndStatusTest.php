<?php

use App\Enums\CalibrationResult;
use App\Enums\EquipmentStatus;
use App\Enums\TestingInstitutionKind;
use App\Filament\Exports\CalibrationScheduleExporter;
use App\Filament\Pages\CalibrationSchedule;
use App\Filament\Resources\Calibrations\Pages\CreateCalibration;
use App\Filament\Resources\Calibrations\Pages\EditCalibration;
use App\Filament\Resources\Equipment\Pages\ListEquipment;
use App\Models\Calibration;
use App\Models\CalibrationReminder;
use App\Models\Equipment;
use App\Models\EquipmentType;
use App\Models\Room;
use App\Models\TestingInstitution;
use App\Models\User;
use App\Notifications\CalibrationDueNotification;
use App\Services\CalibrationService;
use App\Services\EquipmentStatusService;
use Database\Seeders\RoleAndPermissionSeeder;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

function makeEquipment(array $typeAttributes = [], ?EquipmentStatus $status = null, string $code = 'ICU-012'): Equipment
{
    $type = EquipmentType::create([
        'name' => 'Jenis '.$code,
        'requires_calibration' => true,
        'requires_installation' => false,
        'calibration_interval_months' => 12,
        'useful_life_years' => 5,
        'residual_value_percent' => 10,
        ...$typeAttributes,
    ]);

    $equipment = Equipment::create([
        'inventory_code' => $code,
        'name' => 'Infusion Pump',
        'equipment_type_id' => $type->id,
        'room_id' => Room::firstOrCreate(['name' => 'Ruang ICU'], ['building' => 'Utama', 'floor' => '2'])->id,
        'acquisition_date' => '2025-01-15',
        'acquisition_cost' => '30000000.00',
    ]);

    if ($status) {
        $equipment->forceFill(['status' => $status])->saveQuietly();
    }

    return $equipment;
}

function makeInstitution(): TestingInstitution
{
    return TestingInstitution::firstOrCreate(
        ['name' => 'BPAFK Surabaya'],
        ['kind' => TestingInstitutionKind::Bpafk],
    );
}

function passedCalibration(Equipment $equipment, string $testedAt, string $validUntil, string $number = 'CERT-001'): Calibration
{
    return Calibration::create([
        'equipment_id' => $equipment->id,
        'testing_institution_id' => makeInstitution()->id,
        'tested_at' => $testedAt,
        'result' => CalibrationResult::Pass,
        'certificate_number' => $number,
        'valid_until' => $validUntil,
    ]);
}

function calibrationData(Equipment $equipment, array $overrides = []): array
{
    return [
        'equipment_id' => $equipment->id,
        'testing_institution_id' => makeInstitution()->id,
        'result' => 'pass',
        'tested_at' => '2027-01-10',
        'certificate_number' => 'CERT-100',
        'valid_until' => '2028-01-10',
        ...$overrides,
    ];
}

beforeEach(function () {
    $this->travelTo('2027-01-15 09:00:00');
    $this->statusService = app(EquipmentStatusService::class);
});

describe('EquipmentStatusService transitions (PRD §F4)', function () {
    it('moves fit for use to calibration due from H-30, not before', function () {
        $equipment = makeEquipment(status: EquipmentStatus::FitForUse);
        passedCalibration($equipment, '2026-02-15', '2027-02-15'); // 31 days left

        $this->statusService->evaluate($equipment);
        expect($equipment->status)->toBe(EquipmentStatus::FitForUse);

        $this->travelTo('2027-01-16 09:00:00'); // 30 days left
        $this->statusService->evaluate($equipment);
        expect($equipment->status)->toBe(EquipmentStatus::CalibrationDue);
    });

    it('moves calibration due to overdue only after valid_until has passed', function () {
        $equipment = makeEquipment(status: EquipmentStatus::CalibrationDue);
        passedCalibration($equipment, '2026-01-15', '2027-01-15');

        $this->statusService->evaluate($equipment);
        expect($equipment->status)->toBe(EquipmentStatus::CalibrationDue);

        $this->travelTo('2027-01-16 09:00:00');
        $this->statusService->evaluate($equipment);
        expect($equipment->status)->toBe(EquipmentStatus::Overdue);
    });

    it('catches up from fit for use to overdue in one run and writes both histories', function () {
        $equipment = makeEquipment(status: EquipmentStatus::FitForUse);
        passedCalibration($equipment, '2026-01-01', '2027-01-01');

        $this->statusService->evaluate($equipment);

        expect($equipment->status)->toBe(EquipmentStatus::Overdue)
            ->and($equipment->statusHistories()->where('from_status', EquipmentStatus::FitForUse)->exists())->toBeTrue()
            ->and($equipment->statusHistories()->where('to_status', EquipmentStatus::Overdue)->exists())->toBeTrue();
    });

    it('never marks new equipment without a certificate as overdue', function () {
        $equipment = makeEquipment();

        $this->statusService->evaluate($equipment);

        expect($equipment->status)->toBe(EquipmentStatus::CalibrationDue);
    });

    it('moves fit for use to calibration due when moved and the type requires installation', function () {
        $equipment = makeEquipment(['requires_installation' => true], EquipmentStatus::FitForUse);

        $this->statusService->handleMovement($equipment, isReinstallation: false);

        expect($equipment->status)->toBe(EquipmentStatus::CalibrationDue);
    });

    it('moves fit for use to calibration due on reinstallation of any type', function () {
        $equipment = makeEquipment(status: EquipmentStatus::FitForUse);

        $this->statusService->handleMovement($equipment, isReinstallation: true);

        expect($equipment->status)->toBe(EquipmentStatus::CalibrationDue);
    });

    it('keeps the status when moving equipment that needs no installation', function () {
        $equipment = makeEquipment(status: EquipmentStatus::FitForUse);

        $this->statusService->handleMovement($equipment, isReinstallation: false);

        expect($equipment->status)->toBe(EquipmentStatus::FitForUse);
    });

    it('starts repair from fit for use, unfit, calibration due and overdue', function (EquipmentStatus $from) {
        $equipment = makeEquipment(status: $from);

        $this->statusService->startRepair($equipment);

        expect($equipment->status)->toBe(EquipmentStatus::UnderRepair);
    })->with([EquipmentStatus::FitForUse, EquipmentStatus::Unfit, EquipmentStatus::CalibrationDue, EquipmentStatus::Overdue]);

    it('finishes repair to calibration due when the type requires calibration', function () {
        $equipment = makeEquipment(status: EquipmentStatus::UnderRepair);

        $this->statusService->finishRepair($equipment);

        expect($equipment->status)->toBe(EquipmentStatus::CalibrationDue);
    });

    it('finishes repair to calibration due, not fit for use, while the certificate is still valid', function (EquipmentStatus $from) {
        $equipment = makeEquipment(status: $from);
        passedCalibration($equipment, '2026-12-01', '2027-12-01');

        $this->statusService->startRepair($equipment);
        $this->statusService->finishRepair($equipment);

        expect($equipment->fresh()->status)->toBe(EquipmentStatus::CalibrationDue);
    })->with([EquipmentStatus::FitForUse, EquipmentStatus::Unfit, EquipmentStatus::CalibrationDue]);

    it('finishes repair to overdue through calibration due when the certificate has expired', function () {
        $equipment = makeEquipment(status: EquipmentStatus::Overdue);
        passedCalibration($equipment, '2026-01-01', '2027-01-01');

        $this->statusService->startRepair($equipment);
        $this->statusService->finishRepair($equipment);

        expect($equipment->fresh()->status)->toBe(EquipmentStatus::Overdue)
            ->and($equipment->statusHistories()->take(2)->pluck('to_status')->all())
            ->toBe([EquipmentStatus::Overdue, EquipmentStatus::CalibrationDue]);
    });

    it('finishes repair to calibration due when there is no certificate yet', function () {
        $equipment = makeEquipment();

        $this->statusService->startRepair($equipment);
        $this->statusService->finishRepair($equipment);

        expect($equipment->fresh()->status)->toBe(EquipmentStatus::CalibrationDue);
    });

    it('never makes a type without required calibration overdue from a voluntary certificate', function () {
        $equipment = makeEquipment(['requires_calibration' => false], EquipmentStatus::FitForUse);
        passedCalibration($equipment, '2026-01-01', '2027-01-01');

        $this->statusService->evaluate($equipment);

        expect($equipment->status)->toBe(EquipmentStatus::FitForUse);
    });

    it('finishes repair to fit for use when the type does not require calibration', function () {
        $equipment = makeEquipment(['requires_calibration' => false], EquipmentStatus::UnderRepair);

        $this->statusService->finishRepair($equipment);

        expect($equipment->status)->toBe(EquipmentStatus::FitForUse);
    });

    it('starts testing from fit for use, calibration due and overdue', function (EquipmentStatus $from) {
        $equipment = makeEquipment(status: $from);

        $this->statusService->startTesting($equipment);

        expect($equipment->status)->toBe(EquipmentStatus::UnderTesting);
    })->with([EquipmentStatus::FitForUse, EquipmentStatus::CalibrationDue, EquipmentStatus::Overdue]);

    it('moves under testing to fit for use on pass and unfit on fail', function (bool $passed, EquipmentStatus $expected) {
        $equipment = makeEquipment(status: EquipmentStatus::UnderTesting);

        $this->statusService->recordTestResult($equipment, $passed);

        expect($equipment->status)->toBe($expected);
    })->with([
        [true, EquipmentStatus::FitForUse],
        [false, EquipmentStatus::Unfit],
    ]);

    it('rejects a transition outside the table and leaves the data unchanged', function (string $method, array $arguments, EquipmentStatus $from) {
        $equipment = makeEquipment(status: $from);
        $historyCount = $equipment->statusHistories()->count();

        expect(fn () => $this->statusService->{$method}($equipment, ...$arguments))->toThrow(DomainException::class);

        expect($equipment->fresh()->status)->toBe($from)
            ->and($equipment->statusHistories()->count())->toBe($historyCount);
    })->with([
        'repair while under testing' => ['startRepair', [], EquipmentStatus::UnderTesting],
        'repair twice' => ['startRepair', [], EquipmentStatus::UnderRepair],
        'finish repair that never started' => ['finishRepair', [], EquipmentStatus::FitForUse],
        'test while under repair' => ['startTesting', [], EquipmentStatus::UnderRepair],
        'test unfit without repair' => ['startTesting', [], EquipmentStatus::Unfit],
        'test twice' => ['startTesting', [], EquipmentStatus::UnderTesting],
        'pass while overdue' => ['recordTestResult', [true], EquipmentStatus::Overdue],
        'pass while under repair' => ['recordTestResult', [true], EquipmentStatus::UnderRepair],
        'fail while fit for use' => ['recordTestResult', [false], EquipmentStatus::FitForUse],
    ]);

    it('re-reads the status so a stale model cannot apply a transition', function () {
        $equipment = makeEquipment();
        $stale = Equipment::find($equipment->id);

        $this->statusService->startTesting($equipment);

        expect(fn () => $this->statusService->startTesting($stale))->toThrow(DomainException::class);
    });

    it('records from, to, reason and user in status_histories and the activity log', function () {
        $user = User::factory()->create();
        $equipment = makeEquipment();

        $this->statusService->startTesting($equipment, $user->id);

        $history = $equipment->statusHistories()->first();

        expect($history->from_status)->toBe(EquipmentStatus::CalibrationDue)
            ->and($history->to_status)->toBe(EquipmentStatus::UnderTesting)
            ->and($history->reason)->toBe('Alat mulai diuji')
            ->and($history->changed_by)->toBe($user->id)
            ->and(Activity::where('subject_type', Equipment::class)->where('subject_id', $equipment->id)->latest('id')->first()->attribute_changes['attributes']['status'] ?? null)
            ->toBe(EquipmentStatus::UnderTesting->value);
    });
});

describe('CalibrationService (PRD §F2)', function () {
    it('records a pass through under testing to fit for use', function () {
        $user = User::factory()->create();
        $equipment = makeEquipment();

        $calibration = app(CalibrationService::class)->record(calibrationData($equipment), $user->id);

        $equipment->refresh();

        expect($equipment->status)->toBe(EquipmentStatus::FitForUse)
            ->and($calibration->recorded_by)->toBe($user->id)
            ->and($equipment->statusHistories()->pluck('to_status')->all())->toContain(EquipmentStatus::UnderTesting)
            ->and($equipment->latestValidCalibration->is($calibration))->toBeTrue();
    });

    it('records a fail as unfit, without certificate details, keeping the previous due date', function () {
        $equipment = makeEquipment(status: EquipmentStatus::UnderTesting);
        $previous = passedCalibration($equipment, '2026-01-20', '2027-01-20');

        $calibration = app(CalibrationService::class)->record(calibrationData($equipment, [
            'result' => 'fail',
            'testing_institution_id' => null,
        ]));

        expect($equipment->fresh()->status)->toBe(EquipmentStatus::Unfit)
            ->and($calibration->certificate_number)->toBeNull()
            ->and($calibration->valid_until)->toBeNull()
            ->and($equipment->fresh()->latestValidCalibration->is($previous))->toBeTrue();
    });

    it('moves straight to calibration due when the new certificate ends within 30 days', function () {
        $equipment = makeEquipment();

        app(CalibrationService::class)->record(calibrationData($equipment, ['valid_until' => '2027-02-01']));

        expect($equipment->fresh()->status)->toBe(EquipmentStatus::CalibrationDue);
    });

    it('rejects invalid data', function (array $overrides, string $errorField) {
        $equipment = makeEquipment();

        try {
            app(CalibrationService::class)->record(calibrationData($equipment, $overrides));
            $this->fail('Expected a validation error.');
        } catch (ValidationException $exception) {
            expect($exception->errors())->toHaveKey($errorField);
        }

        expect(Calibration::count())->toBe(0)
            ->and($equipment->fresh()->status)->toBe(EquipmentStatus::CalibrationDue);
    })->with([
        'tested in the future' => [['tested_at' => '2027-01-16'], 'tested_at'],
        'pass without certificate number' => [['certificate_number' => null], 'certificate_number'],
        'pass without institution' => [['testing_institution_id' => null], 'testing_institution_id'],
        'pass without valid_until' => [['valid_until' => null], 'valid_until'],
        'valid_until beyond the interval' => [['valid_until' => '2028-01-11'], 'valid_until'],
        'valid_until before the test' => [['tested_at' => '2027-01-10', 'valid_until' => '2027-01-09'], 'valid_until'],
        'certificate already expired' => [['tested_at' => '2026-06-01', 'valid_until' => '2027-01-14'], 'valid_until'],
    ]);

    it('accepts valid_until exactly at tested_at plus the interval, without month overflow', function () {
        $equipment = makeEquipment(['calibration_interval_months' => 1]);
        $data = calibrationData($equipment, ['tested_at' => '2026-12-31', 'valid_until' => '2027-01-31']);

        app(CalibrationService::class)->record($data);

        $other = makeEquipment(['calibration_interval_months' => 2], code: 'ICU-013');
        $overflow = calibrationData($other, ['tested_at' => '2026-12-31', 'valid_until' => '2027-03-01', 'certificate_number' => 'CERT-101']);

        expect(fn () => app(CalibrationService::class)->record($overflow))->toThrow(ValidationException::class);
    });

    it('rejects a certificate number already used by the same institution only', function () {
        $service = app(CalibrationService::class);
        $service->record(calibrationData(makeEquipment()));
        $second = makeEquipment(code: 'ICU-013');

        expect(fn () => $service->record(calibrationData($second)))->toThrow(ValidationException::class);

        $otherInstitution = TestingInstitution::create(['name' => 'Lab Lain', 'kind' => TestingInstitutionKind::Swasta]);
        $service->record(calibrationData($second, ['testing_institution_id' => $otherInstitution->id]));

        expect(Calibration::count())->toBe(2);
    });

    it('recalibrates before H-30 and makes the new certificate the reference', function () {
        $equipment = makeEquipment(status: EquipmentStatus::FitForUse);
        passedCalibration($equipment, '2026-08-01', '2027-08-01'); // 198 days left

        $calibration = app(CalibrationService::class)->record(calibrationData($equipment));

        $equipment->refresh();

        expect($equipment->status)->toBe(EquipmentStatus::FitForUse)
            ->and($equipment->latestValidCalibration->is($calibration))->toBeTrue();

        // The old certificate expiring no longer matters.
        $this->travelTo('2027-08-02 00:10:00');
        $this->statusService->evaluate($equipment);

        expect($equipment->status)->toBe(EquipmentStatus::FitForUse);
    });

    it('uses the newest certificate as the reference even when it ends sooner', function () {
        $equipment = makeEquipment(status: EquipmentStatus::FitForUse);
        passedCalibration($equipment, '2026-12-01', '2027-12-01');

        app(CalibrationService::class)->record(calibrationData($equipment, ['valid_until' => '2027-06-01']));

        $this->travelTo('2027-06-02 00:10:00');
        $this->statusService->evaluate($equipment);

        expect($equipment->status)->toBe(EquipmentStatus::Overdue);
    });

    it('marks fit equipment unfit when an early recalibration fails, even with a valid certificate', function () {
        $equipment = makeEquipment(status: EquipmentStatus::FitForUse);
        passedCalibration($equipment, '2026-08-01', '2027-08-01');

        app(CalibrationService::class)->record(calibrationData($equipment, ['result' => 'fail']));

        expect($equipment->fresh()->status)->toBe(EquipmentStatus::Unfit);
    });

    it('recalibrates after repair, from calibration due and from overdue', function (string $validUntil, EquipmentStatus $afterRepair) {
        $equipment = makeEquipment(status: EquipmentStatus::FitForUse);
        passedCalibration($equipment, '2026-01-01', $validUntil);

        $this->statusService->startRepair($equipment);
        $this->statusService->finishRepair($equipment);
        expect($equipment->fresh()->status)->toBe($afterRepair);

        $calibration = app(CalibrationService::class)->record(calibrationData($equipment));

        $equipment->refresh();

        expect($equipment->status)->toBe(EquipmentStatus::FitForUse)
            ->and($equipment->latestValidCalibration->is($calibration))->toBeTrue();
    })->with([
        'certificate still valid' => ['2027-06-01', EquipmentStatus::CalibrationDue],
        'certificate expired' => ['2027-01-01', EquipmentStatus::Overdue],
    ]);

    it('rejects equipment that is under repair, unfit or inactive', function (EquipmentStatus $status, bool $isActive) {
        $equipment = makeEquipment(status: $status);
        $equipment->update(['is_active' => $isActive]);

        expect(fn () => app(CalibrationService::class)->record(calibrationData($equipment)))
            ->toThrow(ValidationException::class);
    })->with([
        [EquipmentStatus::UnderRepair, true],
        [EquipmentStatus::Unfit, true],
        [EquipmentStatus::CalibrationDue, false],
    ]);

    it('corrects a saved calibration, keeping equipment and result, and re-evaluates the status', function () {
        $service = app(CalibrationService::class);
        $equipment = makeEquipment();
        $calibration = $service->record(calibrationData($equipment));

        $service->update($calibration, [
            'equipment_id' => 999,
            'result' => 'fail',
            'tested_at' => '2027-01-10',
            'testing_institution_id' => $calibration->testing_institution_id,
            'certificate_number' => 'CERT-100',
            'valid_until' => '2027-02-01',
        ]);

        $calibration->refresh();

        expect($calibration->equipment_id)->toBe($equipment->id)
            ->and($calibration->result)->toBe(CalibrationResult::Pass)
            ->and($calibration->valid_until->toDateString())->toBe('2027-02-01')
            ->and($equipment->fresh()->status)->toBe(EquipmentStatus::CalibrationDue);
    });
});

describe('scheduled commands', function () {
    it('evaluates active equipment only', function () {
        $active = makeEquipment(status: EquipmentStatus::CalibrationDue);
        passedCalibration($active, '2026-01-01', '2027-01-01');

        $inactive = makeEquipment(status: EquipmentStatus::CalibrationDue, code: 'ICU-013');
        passedCalibration($inactive, '2026-01-01', '2027-01-01', 'CERT-002');
        $inactive->update(['is_active' => false]);

        $this->artisan('equipment:evaluate-status')->assertSuccessful();

        expect($active->fresh()->status)->toBe(EquipmentStatus::Overdue)
            ->and($inactive->fresh()->status)->toBe(EquipmentStatus::CalibrationDue);
    });

    it('registers both commands in the scheduler', function () {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('equipment:evaluate-status')
            ->expectsOutputToContain('calibration:send-reminders')
            ->assertSuccessful();
    });

    it('sends each reminder once per cycle at H-30, H-7 and H-0, to teknisi and admin only', function () {
        $this->seed(RoleAndPermissionSeeder::class);
        Notification::fake();

        $equipment = makeEquipment(status: EquipmentStatus::FitForUse);
        passedCalibration($equipment, '2026-03-14', '2027-03-14');

        $teknisi = User::where('email', 'teknisi@sigap-alkes.test')->first();
        $admin = User::where('email', 'admin@sigap-alkes.test')->first();
        $kepala = User::where('email', 'kepalaruangan@sigap-alkes.test')->first();

        $this->travelTo('2027-02-11 07:00:00'); // H-31
        $this->artisan('calibration:send-reminders')->assertSuccessful();
        expect(CalibrationReminder::count())->toBe(0);

        foreach (['2027-02-12', '2027-03-07', '2027-03-14'] as $sent => $date) { // H-30, H-7, H-0
            $this->travelTo("{$date} 07:00:00");
            $this->artisan('calibration:send-reminders')->assertSuccessful();
            $this->artisan('calibration:send-reminders')->assertSuccessful(); // same day, no duplicate
            expect(CalibrationReminder::count())->toBe($sent + 1);
        }

        $this->travelTo('2027-03-15 07:00:00'); // past due, H-0 already sent
        $this->artisan('calibration:send-reminders')->assertSuccessful();

        expect(CalibrationReminder::pluck('channel')->unique()->all())->toBe(['database,mail']);
        Notification::assertSentToTimes($teknisi, CalibrationDueNotification::class, 3);
        Notification::assertSentToTimes($admin, CalibrationDueNotification::class, 3);
        Notification::assertNotSentTo($kepala, CalibrationDueNotification::class);
    });

    it('starts a new reminder cycle when a new certificate is issued', function () {
        $this->seed(RoleAndPermissionSeeder::class);
        Notification::fake();

        $equipment = makeEquipment(status: EquipmentStatus::FitForUse);
        passedCalibration($equipment, '2026-02-01', '2027-02-01');

        $this->artisan('calibration:send-reminders')->assertSuccessful();

        $this->travelTo('2027-02-01 07:00:00');
        passedCalibration($equipment, '2027-01-30', '2027-02-20', 'CERT-002'); // new cycle, 19 days left
        $this->artisan('calibration:send-reminders')->assertSuccessful();

        expect(CalibrationReminder::where('kind', 'h30')->count())->toBe(2);
    });

    it('sends reminders from the newest certificate only after an early recalibration', function () {
        $this->seed(RoleAndPermissionSeeder::class);
        Notification::fake();

        $equipment = makeEquipment(status: EquipmentStatus::FitForUse);
        passedCalibration($equipment, '2026-08-01', '2027-08-01');
        app(CalibrationService::class)->record(calibrationData($equipment));

        // 22 days before the old certificate ends: nothing is due anymore.
        $this->travelTo('2027-07-10 07:00:00');
        $this->artisan('calibration:send-reminders')->assertSuccessful();
        expect(CalibrationReminder::count())->toBe(0);

        // 30 days before the new certificate ends.
        $this->travelTo('2027-12-11 07:00:00');
        $this->artisan('calibration:send-reminders')->assertSuccessful();
        expect(CalibrationReminder::sole()->cycle_valid_until->toDateString())->toBe('2028-01-10');
    });

    it('catches up with the most urgent reminder after a missed day', function () {
        $this->seed(RoleAndPermissionSeeder::class);
        Notification::fake();

        $equipment = makeEquipment(status: EquipmentStatus::CalibrationDue);
        passedCalibration($equipment, '2026-01-20', '2027-01-20'); // 5 days left: H-7 applies

        $this->artisan('calibration:send-reminders')->assertSuccessful();

        expect(CalibrationReminder::sole()->kind->value)->toBe('h7');
    });

    it('skips equipment that is inactive, being handled, or has no certificate', function () {
        $this->seed(RoleAndPermissionSeeder::class);
        Notification::fake();

        foreach ([EquipmentStatus::UnderTesting, EquipmentStatus::UnderRepair, EquipmentStatus::Unfit] as $index => $status) {
            passedCalibration(makeEquipment(status: $status, code: "A-{$index}"), '2026-01-20', '2027-01-20', "C-{$index}");
        }

        $inactive = makeEquipment(status: EquipmentStatus::CalibrationDue, code: 'B-1');
        passedCalibration($inactive, '2026-01-20', '2027-01-20', 'C-9');
        $inactive->update(['is_active' => false]);

        makeEquipment(code: 'B-2');

        $this->artisan('calibration:send-reminders')->assertSuccessful();

        Notification::assertNothingSent();
        expect(CalibrationReminder::count())->toBe(0);
    });

    it('records nothing when there is no recipient, so the reminder is not lost', function () {
        $this->seed(RoleAndPermissionSeeder::class);
        Notification::fake();
        User::query()->delete();

        passedCalibration(makeEquipment(status: EquipmentStatus::CalibrationDue), '2026-01-20', '2027-01-20');

        $this->artisan('calibration:send-reminders')->assertFailed();

        expect(CalibrationReminder::count())->toBe(0);
    });

    it('delivers a Filament database notification and a mail in Indonesian', function () {
        $this->seed(RoleAndPermissionSeeder::class);

        $equipment = makeEquipment(status: EquipmentStatus::FitForUse);
        passedCalibration($equipment, '2026-03-14', '2027-03-14');
        $this->travelTo('2027-03-07 07:00:00');

        $this->artisan('calibration:send-reminders')->assertSuccessful();

        $teknisi = User::where('email', 'teknisi@sigap-alkes.test')->first();
        $message = 'Kalibrasi Infusion Pump (ICU-012) jatuh tempo 7 hari lagi (14 Maret 2027).';

        expect($teknisi->notifications()->sole()->data['format'])->toBe('filament')
            ->and($teknisi->notifications()->sole()->data['body'])->toBe($message);

        $mail = (new CalibrationDueNotification($equipment, $equipment->latestValidCalibration->valid_until, 7))
            ->toMail($teknisi)
            ->render();

        expect((string) $mail)->toContain('Kalibrasi Infusion Pump (ICU-012)')
            ->not->toContain('Hello!')
            ->not->toContain('trouble clicking');
    });

    it('words the message for today and for past due dates', function () {
        $equipment = makeEquipment();
        $date = today();

        expect((new CalibrationDueNotification($equipment, $date, 0))->message())
            ->toBe('Kalibrasi Infusion Pump (ICU-012) jatuh tempo hari ini (15 Januari 2027).')
            ->and((new CalibrationDueNotification($equipment, $date, -3))->message())
            ->toBe('Kalibrasi Infusion Pump (ICU-012) sudah lewat jatuh tempo 3 hari (15 Januari 2027).');
    });
});

describe('Filament pages', function () {
    beforeEach(function () {
        $this->seed(RoleAndPermissionSeeder::class);
        $this->teknisi = User::where('email', 'teknisi@sigap-alkes.test')->first();
    });

    it('records a calibration from the form, prefilled from the equipment page', function () {
        $equipment = makeEquipment();

        Livewire::actingAs($this->teknisi)
            ->withQueryParams(['equipment_id' => $equipment->id])
            ->test(CreateCalibration::class)
            ->assertSchemaStateSet(['equipment_id' => $equipment->id])
            ->fillForm([
                'testing_institution_id' => makeInstitution()->id,
                'result' => 'pass',
                'tested_at' => '2027-01-10',
                'certificate_number' => 'CERT-200',
                'valid_until' => '2028-01-10',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect($equipment->fresh()->status)->toBe(EquipmentStatus::FitForUse)
            ->and(Calibration::sole()->recorded_by)->toBe($this->teknisi->id);
    });

    it('shows service validation errors under the form fields, in Indonesian', function () {
        $equipment = makeEquipment();

        Livewire::actingAs($this->teknisi)
            ->test(CreateCalibration::class)
            ->fillForm([
                'equipment_id' => $equipment->id,
                'testing_institution_id' => makeInstitution()->id,
                'result' => 'pass',
                'tested_at' => '2027-01-10',
                'certificate_number' => 'CERT-200',
                'valid_until' => '2029-01-10',
            ])
            ->call('create')
            ->assertHasFormErrors(['valid_until'])
            ->assertSee('Berlaku sampai tidak boleh melewati 10 Januari 2028');

        expect(Calibration::count())->toBe(0);
    });

    it('translates required-field errors instead of showing the raw key', function () {
        Livewire::actingAs($this->teknisi)
            ->test(CreateCalibration::class)
            ->fillForm(['equipment_id' => null])
            ->call('create')
            ->assertSee('wajib diisi')
            ->assertDontSee('validation.required');
    });

    it('hides certificate fields when the result is fail', function () {
        $equipment = makeEquipment();

        Livewire::actingAs($this->teknisi)
            ->test(CreateCalibration::class)
            ->fillForm(['equipment_id' => $equipment->id, 'result' => 'fail', 'tested_at' => '2027-01-10'])
            ->assertFormFieldHidden('certificate_number')
            ->call('create')
            ->assertHasNoFormErrors();

        expect($equipment->fresh()->status)->toBe(EquipmentStatus::Unfit);
    });

    it('edits a calibration without changing its result', function () {
        $equipment = makeEquipment();
        $calibration = app(CalibrationService::class)->record(calibrationData($equipment));

        Livewire::actingAs($this->teknisi)
            ->test(EditCalibration::class, ['record' => $calibration->id])
            ->fillForm(['certificate_number' => 'CERT-EDIT'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($calibration->fresh()->certificate_number)->toBe('CERT-EDIT')
            ->and($calibration->fresh()->result)->toBe(CalibrationResult::Pass);
    });

    it('enforces calibration access per role and never allows delete', function () {
        $calibration = app(CalibrationService::class)->record(calibrationData(makeEquipment()));
        $url = "/admin/calibrations/{$calibration->id}/edit";

        $this->actingAs($this->teknisi)->get('/admin/calibrations')->assertOk();
        $this->get('/admin/calibrations/create')->assertOk();
        $this->get($url)->assertOk();
        $this->get('/admin/jadwal-kalibrasi')->assertOk();
        expect($this->teknisi->can('delete', $calibration))->toBeFalse();

        foreach (['admin@sigap-alkes.test', 'manajemen@sigap-alkes.test'] as $email) {
            $this->flushSession();
            $this->actingAs(User::where('email', $email)->first());
            $this->get('/admin/calibrations')->assertOk();
            $this->get('/admin/jadwal-kalibrasi')->assertOk();
            $this->get('/admin/calibrations/create')->assertForbidden();
            $this->get($url)->assertForbidden();
        }

        $this->flushSession();
        $this->actingAs(User::where('email', 'kepalaruangan@sigap-alkes.test')->first());
        $this->get('/admin/calibrations')->assertForbidden();
        $this->get('/admin/jadwal-kalibrasi')->assertForbidden();
    });

    it('offers start testing and record actions only to teknisi, not during repair', function () {
        $equipment = makeEquipment();
        $kepala = User::where('email', 'kepalaruangan@sigap-alkes.test')->first();

        $this->actingAs($this->teknisi)->get("/admin/equipment/{$equipment->id}")
            ->assertOk()
            ->assertSee('Mulai uji &amp; kalibrasi', false)
            ->assertSee('Catat hasil kalibrasi');

        $this->flushSession();
        $this->actingAs($kepala)->get("/admin/equipment/{$equipment->id}")
            ->assertOk()
            ->assertDontSee('Catat hasil kalibrasi');

        $this->flushSession();
        $equipment->forceFill(['status' => EquipmentStatus::FitForUse])->saveQuietly();
        $this->actingAs($this->teknisi)->get("/admin/equipment/{$equipment->id}")
            ->assertSee('Mulai uji &amp; kalibrasi', false)
            ->assertSee('Catat hasil kalibrasi');

        $this->flushSession();
        $equipment->forceFill(['status' => EquipmentStatus::UnderRepair])->saveQuietly();
        $this->actingAs($this->teknisi)->get("/admin/equipment/{$equipment->id}")
            ->assertDontSee('Mulai uji &amp; kalibrasi', false)
            ->assertDontSee('Catat hasil kalibrasi');
    });

    it('filters the schedule by due month and excludes equipment that needs no calibration', function () {
        $march = makeEquipment(status: EquipmentStatus::FitForUse, code: 'MAR-1');
        passedCalibration($march, '2026-03-31', '2027-03-31');

        $april = makeEquipment(status: EquipmentStatus::FitForUse, code: 'APR-1');
        passedCalibration($april, '2026-04-01', '2027-04-01', 'CERT-002');

        $noCertificate = makeEquipment(code: 'NEW-1');
        $noCalibrationNeeded = makeEquipment(['requires_calibration' => false], code: 'SKIP-1');

        Livewire::actingAs($this->teknisi)
            ->test(CalibrationSchedule::class)
            ->assertCanSeeTableRecords([$march, $april, $noCertificate])
            ->assertCanNotSeeTableRecords([$noCalibrationNeeded])
            ->filterTable('due_month', '2027-03')
            ->assertCanSeeTableRecords([$march])
            ->assertCanNotSeeTableRecords([$april, $noCertificate]);
    });

    it('ignores a tampered month filter value', function () {
        $equipment = makeEquipment();

        Livewire::actingAs($this->teknisi)
            ->test(CalibrationSchedule::class)
            ->filterTable('due_month', 'not-a-month')
            ->assertCanSeeTableRecords([$equipment]);
    });

    it('exports the schedule to a file without cost data', function () {
        Storage::fake('local');

        $equipment = makeEquipment(status: EquipmentStatus::FitForUse);
        passedCalibration($equipment, '2026-03-14', '2027-03-14');

        Livewire::actingAs($this->teknisi)
            ->test(CalibrationSchedule::class)
            ->callAction(TestAction::make('export')->table(), ['fileName' => 'jadwal'])
            ->assertHasNoActionErrors();

        $export = Export::sole();
        $csv = collect(Storage::disk('local')->files($export->getFileDirectory()))
            ->filter(fn (string $file) => str_ends_with($file, '.csv'))
            ->map(fn (string $file) => Storage::disk('local')->get($file))
            ->implode('');

        expect($export->exporter)->toBe(CalibrationScheduleExporter::class)
            ->and($export->successful_rows)->toBe(1)
            ->and($csv)->toContain('ICU-012')
            ->toContain('Laik pakai')
            ->toContain('14/03/2027')
            ->not->toContain('30000000');
    });

    it('filters the equipment list by due within 30 days', function () {
        $soon = makeEquipment(status: EquipmentStatus::FitForUse, code: 'SOON-1');
        passedCalibration($soon, '2026-02-14', '2027-02-14'); // 30 days left

        $later = makeEquipment(status: EquipmentStatus::FitForUse, code: 'LATE-1');
        passedCalibration($later, '2026-02-15', '2027-02-15', 'CERT-002'); // 31 days left

        Livewire::actingAs($this->teknisi)
            ->test(ListEquipment::class)
            ->filterTable('due_soon')
            ->assertCanSeeTableRecords([$soon])
            ->assertCanNotSeeTableRecords([$later]);
    });
});

describe('public QR page', function () {
    it('shows the certificate date without leaking the certificate number or institution', function () {
        $user = User::factory()->create(['name' => 'Teknisi Rahasia']);
        $equipment = makeEquipment();
        app(CalibrationService::class)->record(calibrationData($equipment), $user->id);

        $this->get("/alat/{$equipment->qr_token}")
            ->assertOk()
            ->assertSee('LAIK PAKAI')
            ->assertSee('10 Januari 2028')
            ->assertDontSee('CERT-100')
            ->assertDontSee('BPAFK Surabaya')
            ->assertDontSee('Teknisi Rahasia');
    });
});
