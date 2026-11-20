<?php

namespace App\Services;

use App\Enums\CalibrationResult;
use App\Models\Equipment;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ComplianceCalculator
{
    /**
     * Snapshot of calibration compliance on a given date (default: today), per PRD §F5:
     * K = Ns / Nw * 100%
     * - Nw: active equipment whose type requires calibration (existing on asOf)
     * - Ns: subset of Nw with a passed calibration performed on or before asOf whose valid_until >= asOf
     * When Nw = 0, returns null percent and "–" formatted string (never divides by zero).
     *
     * @return array{
     *     required_count: int,
     *     compliant_count: int,
     *     compliance_percent: float|null,
     *     formatted: string
     * }
     */
    public function calculate(?int $roomId = null, ?CarbonInterface $asOf = null): array
    {
        $referenceDate = ($asOf ? Carbon::instance($asOf) : today())->copy()->startOfDay();
        $isHistorical = $asOf !== null && $referenceDate->lt(today());

        $equipmentList = Equipment::query()
            ->where('is_active', true)
            ->when($roomId, fn (Builder $q) => $q->where('room_id', $roomId))
            ->whereHas('equipmentType', fn (Builder $q) => $q->where('requires_calibration', true))
            ->when(
                $isHistorical,
                fn (Builder $q) => $q->with(['calibrations' => fn ($c) => $c->where('result', CalibrationResult::Pass)]),
                fn (Builder $q) => $q->with('latestValidCalibration'),
            )
            ->get();

        return $this->evaluateAtDate($equipmentList, $referenceDate, $isHistorical);
    }

    /**
     * 12-month end-of-month compliance series (oldest to newest) for sparkline & delta.
     *
     * @return array<int, array{month: string, label: string, compliance_percent: float|null, required_count: int, compliant_count: int}>
     */
    public function monthlySeries(int $months = 12, ?int $roomId = null, ?CarbonInterface $asOf = null): array
    {
        $referenceDate = ($asOf ? Carbon::instance($asOf) : today())->copy()->startOfDay();

        $equipmentList = Equipment::query()
            ->where('is_active', true)
            ->when($roomId, fn (Builder $q) => $q->where('room_id', $roomId))
            ->whereHas('equipmentType', fn (Builder $q) => $q->where('requires_calibration', true))
            ->with(['calibrations' => fn ($c) => $c->where('result', CalibrationResult::Pass)])
            ->get();

        $series = [];

        for ($offset = $months - 1; $offset >= 0; $offset--) {
            $pointDate = $offset === 0
                ? $referenceDate->copy()
                : $referenceDate->copy()->startOfMonth()->subMonthsNoOverflow($offset)->endOfMonth()->startOfDay();

            $eval = $this->evaluateAtDate($equipmentList, $pointDate, $pointDate->lt(today()));

            $series[] = [
                'month' => $pointDate->format('Y-m'),
                'label' => $pointDate->translatedFormat('M'),
                'compliance_percent' => $eval['compliance_percent'],
                'required_count' => $eval['required_count'],
                'compliant_count' => $eval['compliant_count'],
            ];
        }

        return $series;
    }

    /**
     * @param  Collection<int, Equipment>  $equipmentList
     * @return array{required_count: int, compliant_count: int, compliance_percent: float|null, formatted: string}
     */
    private function evaluateAtDate(Collection $equipmentList, Carbon $referenceDate, bool $isHistorical): array
    {
        $endOfRefDay = $referenceDate->copy()->endOfDay();

        $eligible = $isHistorical
            ? $equipmentList->filter(fn (Equipment $eq): bool => $eq->created_at === null || $eq->created_at->lessThanOrEqualTo($endOfRefDay))
            : $equipmentList;

        $nw = $eligible->count();

        if ($nw === 0) {
            return [
                'required_count' => 0,
                'compliant_count' => 0,
                'compliance_percent' => null,
                'formatted' => '–',
            ];
        }

        $ns = $eligible->filter(function (Equipment $equipment) use ($referenceDate, $endOfRefDay, $isHistorical): bool {
            if (! $isHistorical && $equipment->relationLoaded('latestValidCalibration')) {
                $validUntil = $equipment->latestValidCalibration?->valid_until;

                return $validUntil !== null && $validUntil->copy()->startOfDay()->greaterThanOrEqualTo($referenceDate);
            }

            return $equipment->calibrations->contains(function ($cal) use ($referenceDate, $endOfRefDay): bool {
                if ($cal->valid_until === null) {
                    return false;
                }

                $testedAtOk = $cal->tested_at === null || $cal->tested_at->copy()->startOfDay()->lessThanOrEqualTo($endOfRefDay);

                return $testedAtOk && $cal->valid_until->copy()->startOfDay()->greaterThanOrEqualTo($referenceDate);
            });
        })->count();

        $percent = round(($ns / $nw) * 100, 2);

        return [
            'required_count' => $nw,
            'compliant_count' => $ns,
            'compliance_percent' => $percent,
            'formatted' => number_format($percent, 1, ',', '.').'%',
        ];
    }
}
