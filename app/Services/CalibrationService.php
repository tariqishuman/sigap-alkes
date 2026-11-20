<?php

namespace App\Services;

use App\Enums\CalibrationResult;
use App\Enums\EquipmentStatus;
use App\Models\Calibration;
use App\Models\Equipment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CalibrationService
{
    public function __construct(private EquipmentStatusService $statusService) {}

    /**
     * Save a finished test and apply its status transition.
     * Equipment not yet marked "under testing" passes through that status first,
     * so a result entered after the fact still follows the PRD §F4 table.
     */
    public function record(array $data, ?int $recordedBy = null): Calibration
    {
        $actor = $recordedBy ? User::find($recordedBy) : Auth::user();
        $data = $this->enforceInstitutionScope($data, $actor);

        $validated = $this->validate($data);
        $equipment = Equipment::findOrFail($validated['equipment_id']);

        return DB::transaction(function () use ($equipment, $validated, $recordedBy) {
            if ($equipment->status !== EquipmentStatus::UnderTesting) {
                $this->statusService->startTesting($equipment, $recordedBy);
            }

            $calibration = Calibration::create([...$validated, 'recorded_by' => $recordedBy]);

            $this->statusService->recordTestResult(
                $equipment,
                $calibration->result === CalibrationResult::Pass,
                $recordedBy,
            );

            // A certificate that is already within 30 days moves straight on to "Perlu kalibrasi".
            $this->statusService->evaluate($equipment);

            return $calibration;
        });
    }

    /**
     * Correct a saved calibration. Equipment and result stay fixed because
     * they already drove a status transition.
     */
    public function update(Calibration $calibration, array $data, ?int $actorId = null): Calibration
    {
        $actor = $actorId ? User::find($actorId) : Auth::user();

        if ($actor instanceof User && $actor->isBadanKalibrasiOnly()) {
            if ($actor->testing_institution_id === null || (int) $calibration->testing_institution_id !== (int) $actor->testing_institution_id) {
                throw ValidationException::withMessages([
                    'testing_institution_id' => 'Badan Kalibrasi hanya boleh mengubah kalibrasi milik lembaganya sendiri.',
                ]);
            }
        }

        $data = $this->enforceInstitutionScope($data, $actor);

        $validated = $this->validate([
            ...$data,
            'equipment_id' => $calibration->equipment_id,
            'result' => $calibration->result->value,
        ], $calibration);

        return DB::transaction(function () use ($calibration, $validated) {
            $calibration->update($validated);
            $this->statusService->evaluate($calibration->equipment);

            return $calibration;
        });
    }

    /**
     * Ensure badan_kalibrasi users can only record/update calibrations under their own testing institution.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function enforceInstitutionScope(array $data, mixed $actor): array
    {
        if (! ($actor instanceof User) || ! $actor->isBadanKalibrasiOnly()) {
            return $data;
        }

        if ($actor->testing_institution_id === null) {
            throw ValidationException::withMessages([
                'testing_institution_id' => 'Akun Badan Kalibrasi belum terhubung ke lembaga penguji.',
            ]);
        }

        if (
            isset($data['testing_institution_id'])
            && $data['testing_institution_id'] !== ''
            && (int) $data['testing_institution_id'] !== (int) $actor->testing_institution_id
        ) {
            throw ValidationException::withMessages([
                'testing_institution_id' => 'Badan Kalibrasi hanya boleh mencatat kalibrasi atas nama lembaganya sendiri.',
            ]);
        }

        $data['testing_institution_id'] = $actor->testing_institution_id;

        return $data;
    }

    /**
     * Validation rules from PRD §F2.
     */
    private function validate(array $data, ?Calibration $existing = null): array
    {
        $data = array_merge([
            'testing_institution_id' => null,
            'certificate_number' => null,
            'valid_until' => null,
            'certificate_path' => null,
        ], $data);

        // A failed test issues no certificate.
        if (($data['result'] ?? null) === CalibrationResult::Fail->value) {
            $data['certificate_number'] = null;
            $data['valid_until'] = null;
            $data['certificate_path'] = null;
        }

        $equipment = Equipment::with('equipmentType')->find($data['equipment_id'] ?? null);

        return Validator::make($data, [
            'equipment_id' => ['required', function ($attribute, $value, $fail) use ($equipment, $existing) {
                if ($existing) {
                    return;
                }

                if (! $equipment?->is_active || ! in_array($equipment->status, EquipmentStatus::CALIBRATABLE, true)) {
                    $fail('Alat ini tidak dalam status yang bisa dicatat hasil kalibrasinya.');
                }
            }],
            'result' => ['required', Rule::enum(CalibrationResult::class)],
            'tested_at' => ['required', 'date', 'before_or_equal:today'],
            'testing_institution_id' => ['nullable', 'required_if:result,pass', 'exists:testing_institutions,id'],
            'certificate_number' => [
                'nullable',
                'required_if:result,pass',
                'string',
                'max:255',
                Rule::unique('calibrations')
                    ->where('testing_institution_id', $data['testing_institution_id'])
                    ->ignore($existing),
            ],
            'valid_until' => array_filter([
                'nullable',
                'required_if:result,pass',
                'date',
                'after:tested_at',
                // Saving an expired certificate would show the equipment as fit for use.
                $existing ? null : 'after_or_equal:today',
                function ($attribute, $value, $fail) use ($data, $equipment) {
                    if (! $equipment || ! strtotime((string) ($data['tested_at'] ?? ''))) {
                        return;
                    }

                    $months = $equipment->equipmentType->calibration_interval_months;
                    $latest = Carbon::parse($data['tested_at'])->addMonthsNoOverflow($months);

                    if (Carbon::parse($value)->greaterThan($latest)) {
                        $fail("Berlaku sampai tidak boleh melewati {$latest->translatedFormat('d F Y')} (tanggal uji + {$months} bulan).");
                    }
                },
            ]),
            'certificate_path' => ['nullable', 'string', 'max:255'],
        ], [
            'certificate_number.unique' => 'Nomor sertifikat ini sudah dipakai untuk lembaga penguji yang sama.',
        ], [
            'equipment_id' => 'alat',
            'result' => 'hasil',
            'tested_at' => 'tanggal uji',
            'testing_institution_id' => 'lembaga penguji',
            'certificate_number' => 'nomor sertifikat',
            'valid_until' => 'berlaku sampai',
        ])->validate();
    }
}
