<?php

namespace App\Services;

use App\Models\Equipment;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class DepreciationCalculator
{
    /**
     * Straight-line depreciation calculation using bcmath (PRD §F6):
     * P = (HP - NR) / MM
     * NB = max(NR, HP - (P * n)), where n = full months since acquisition_date / 12
     *
     * @return array{
     *     acquisition_cost: string,
     *     residual_value: string,
     *     useful_life_years: int,
     *     full_months_elapsed: int,
     *     annual_depreciation: string,
     *     accumulated_depreciation: string,
     *     book_value: string
     * }
     */
    public function calculateForEquipment(Equipment $equipment, ?CarbonInterface $asOf = null): array
    {
        $equipment->loadMissing('equipmentType');

        $acquisitionCost = (string) ($equipment->acquisition_cost ?? '0.00');
        $usefulLifeYears = max(1, (int) ($equipment->equipmentType?->useful_life_years ?? 5));

        if ($equipment->residual_value !== null) {
            $residualValue = (string) $equipment->residual_value;
        } else {
            $residualPercent = (string) ($equipment->equipmentType?->residual_value_percent ?? 10);
            $residualValue = bcdiv(bcmul($acquisitionCost, $residualPercent, 4), '100', 2);
        }

        $acquisitionDate = $equipment->acquisition_date instanceof CarbonInterface
            ? $equipment->acquisition_date->copy()->startOfDay()
            : Carbon::parse($equipment->acquisition_date)->startOfDay();

        $referenceDate = ($asOf ? Carbon::instance($asOf) : now())->copy()->startOfDay();

        $fullMonths = $referenceDate->greaterThan($acquisitionDate)
            ? (int) $acquisitionDate->diffInMonths($referenceDate)
            : 0;

        return $this->calculate(
            acquisitionCost: $acquisitionCost,
            residualValue: $residualValue,
            usefulLifeYears: $usefulLifeYears,
            fullMonthsElapsed: $fullMonths,
        );
    }

    /**
     * Pure calculation method for straight-line depreciation using bcmath.
     *
     * @return array{
     *     acquisition_cost: string,
     *     residual_value: string,
     *     useful_life_years: int,
     *     full_months_elapsed: int,
     *     annual_depreciation: string,
     *     accumulated_depreciation: string,
     *     book_value: string
     * }
     */
    public function calculate(
        string|int|float $acquisitionCost,
        string|int|float $residualValue,
        int $usefulLifeYears,
        int $fullMonthsElapsed,
    ): array {
        $hp = bcadd((string) $acquisitionCost, '0', 2);
        $nr = bcadd((string) $residualValue, '0', 2);
        $mm = (string) max(1, $usefulLifeYears);
        $months = max(0, $fullMonthsElapsed);

        // Depreciable base = max(0, HP - NR)
        $depreciableBase = bcsub($hp, $nr, 4);
        if (bccomp($depreciableBase, '0', 4) < 0) {
            $depreciableBase = '0.0000';
        }

        // P = (HP - NR) / MM
        $annualDepreciationRaw = bcdiv($depreciableBase, $mm, 4);
        $annualDepreciation = bcadd($annualDepreciationRaw, '0', 2);

        // P * n where n = fullMonths / 12 -> (P * fullMonths) / 12
        $accumulatedRaw = bcdiv(bcmul($annualDepreciationRaw, (string) $months, 4), '12', 4);
        if (bccomp($accumulatedRaw, $depreciableBase, 4) > 0) {
            $accumulatedRaw = $depreciableBase;
        }
        $accumulatedDepreciation = bcadd($accumulatedRaw, '0', 2);

        // NB = HP - accumulated, with lower bound NB >= NR
        $bookValue = bcsub($hp, $accumulatedDepreciation, 2);
        if (bccomp($bookValue, $nr, 2) < 0) {
            $bookValue = $nr;
        }

        return [
            'acquisition_cost' => $hp,
            'residual_value' => $nr,
            'useful_life_years' => max(1, $usefulLifeYears),
            'full_months_elapsed' => $months,
            'annual_depreciation' => $annualDepreciation,
            'accumulated_depreciation' => $accumulatedDepreciation,
            'book_value' => $bookValue,
        ];
    }

    /**
     * Check whether cumulative repair cost >= X% of current book value (PRD §F6).
     * Uses bcmath multiplication (repairCost * 100 >= bookValue * X) to avoid division rounding.
     */
    public function shouldConsiderReplacement(
        Equipment $equipment,
        ?CarbonInterface $asOf = null,
        ?int $thresholdPercent = null,
    ): bool {
        $threshold = $thresholdPercent ?? (int) config('sigap.replace_threshold_percent', 50);
        $repairCost = $equipment->totalRepairCost();
        $bookValue = $this->calculateForEquipment($equipment, $asOf)['book_value'];

        return $this->meetsReplacementThreshold($repairCost, $bookValue, $threshold);
    }

    public function meetsReplacementThreshold(
        string $repairCost,
        string $bookValue,
        int $thresholdPercent,
    ): bool {
        $repair = bcadd($repairCost, '0', 2);
        $book = bcadd($bookValue, '0', 2);

        if (bccomp($repair, '0.00', 2) <= 0) {
            return false;
        }

        $left = bcmul($repair, '100', 2);
        $right = bcmul($book, (string) max(0, $thresholdPercent), 2);

        return bccomp($left, $right, 2) >= 0;
    }
}
