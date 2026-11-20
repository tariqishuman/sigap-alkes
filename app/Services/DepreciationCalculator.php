<?php

namespace App\Services;

use App\Models\Equipment;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class DepreciationCalculator
{
    /**
     * Straight-line monthly depreciation calculation using bcmath (PRD §F6):
     * Monthly depreciation = (HP - NR) / (MM * 12)
     * Book value = max(NR, HP - ((HP - NR) * n / (MM * 12))), rounded to 2 decimals at the end.
     *
     * @return array{
     *     acquisition_cost: string,
     *     residual_value: string,
     *     useful_life_years: int,
     *     full_months_elapsed: int,
     *     monthly_depreciation: string,
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
     * Pure calculation method for straight-line monthly depreciation using bcmath.
     *
     * @return array{
     *     acquisition_cost: string,
     *     residual_value: string,
     *     useful_life_years: int,
     *     full_months_elapsed: int,
     *     monthly_depreciation: string,
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
        $mm = max(1, $usefulLifeYears);
        $totalMonths = (string) ($mm * 12);
        $months = max(0, $fullMonthsElapsed);

        // Depreciable base = max(0, HP - NR)
        $depreciableBase = bcsub($hp, $nr, 8);
        if (bccomp($depreciableBase, '0', 8) < 0) {
            $depreciableBase = '0.00000000';
        }

        // Monthly depreciation = (HP - NR) / (MM * 12)
        $monthlyRaw = bcdiv($depreciableBase, $totalMonths, 8);
        $monthlyDepreciation = $this->roundHalfUp($monthlyRaw, 2);

        // Annual depreciation kept for reference: (HP - NR) / MM
        $annualRaw = bcdiv($depreciableBase, (string) $mm, 8);
        $annualDepreciation = $this->roundHalfUp($annualRaw, 2);

        // Accumulated = min(HP - NR, (HP - NR) * n / (MM * 12)) -> direct formula avoids rounding drift
        $accumulatedRaw = bcdiv(bcmul($depreciableBase, (string) $months, 8), $totalMonths, 8);
        if (bccomp($accumulatedRaw, $depreciableBase, 8) > 0) {
            $accumulatedRaw = $depreciableBase;
        }
        $accumulatedDepreciation = $this->roundHalfUp($accumulatedRaw, 2);

        // NB = max(NR, HP - accumulated)
        $bookValue = bcsub($hp, $accumulatedDepreciation, 2);
        if (bccomp($bookValue, $nr, 2) < 0) {
            $bookValue = $nr;
        }

        return [
            'acquisition_cost' => $hp,
            'residual_value' => $nr,
            'useful_life_years' => $mm,
            'full_months_elapsed' => $months,
            'monthly_depreciation' => $monthlyDepreciation,
            'annual_depreciation' => $annualDepreciation,
            'accumulated_depreciation' => $accumulatedDepreciation,
            'book_value' => $bookValue,
        ];
    }

    private function roundHalfUp(string $value, int $precision): string
    {
        $factor = '0.'.str_repeat('0', $precision).'5';

        if (bccomp($value, '0', $precision + 2) >= 0) {
            return bcadd($value, $factor, $precision);
        }

        return bcsub($value, $factor, $precision);
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
        $threshold = $thresholdPercent ?? app(SettingsService::class)->replaceThresholdPercent();
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
