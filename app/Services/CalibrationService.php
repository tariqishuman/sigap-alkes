<?php

namespace App\Services;

use App\Enums\CalibrationResult;
use App\Enums\EquipmentStatus;
use App\Models\Calibration;
use App\Models\Equipment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

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
    public function update(Calibration $calibration, array $data): Calibration
    {
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
