<?php

namespace App\Services;

use App\Models\Equipment;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class ComplianceCalculator
{
    /**
     * Snapshot of calibration compliance on a given date (default: today), per PRD §F5:
     * K = Ns / Nw * 100%
     * - Nw: active equipment whose type requires calibration
     * - Ns: subset of Nw whose latest passed calibration has valid_until >= today
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

        $equipmentList = Equipment::query()
            ->where('is_active', true)
            ->when($roomId, fn (Builder $q) => $q->where('room_id', $roomId))
            ->whereHas('equipmentType', fn (Builder $q) => $q->where('requires_calibration', true))
            ->with('latestValidCalibration')
            ->get();

        $nw = $equipmentList->count();

        if ($nw === 0) {
            return [
                'required_count' => 0,
                'compliant_count' => 0,
                'compliance_percent' => null,
                'formatted' => '–',
            ];
        }

        $ns = $equipmentList->filter(function (Equipment $equipment) use ($referenceDate): bool {
            $validUntil = $equipment->latestValidCalibration?->valid_until;

            return $validUntil !== null && $validUntil->copy()->startOfDay()->greaterThanOrEqualTo($referenceDate);
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
