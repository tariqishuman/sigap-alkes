<?php

namespace App\Services;

use App\Enums\EquipmentStatus;
use App\Models\Equipment;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class UptimeCalculator
{
    /** Downtime statuses per PRD §F5. UnderTesting is counted as uptime. */
    private const DOWNTIME_STATUSES = [
        EquipmentStatus::UnderRepair,
        EquipmentStatus::Unfit,
    ];

    /**
     * Calculate uptime for a single active equipment within [periodStart, periodEnd].
     * T starts at max(periodStart, equipment.created_at) and ends at min(periodEnd, now).
     *
     * @return array{
     *     total_seconds: int,
     *     downtime_seconds: int,
     *     total_hours: float,
     *     downtime_hours: float,
     *     uptime_percent: float|null
     * }
     */
    public function forEquipment(
        Equipment $equipment,
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd,
        ?CarbonInterface $now = null,
    ): array {
        if (! $equipment->is_active) {
            return $this->emptyResult();
        }

        $referenceNow = $now ? Carbon::instance($now) : now();
        $createdAt = $equipment->created_at ? Carbon::instance($equipment->created_at) : Carbon::instance($periodStart);

        $effStart = Carbon::instance($periodStart)->greaterThan($createdAt)
            ? Carbon::instance($periodStart)
            : $createdAt->copy();

        $effEnd = Carbon::instance($periodEnd)->lessThan($referenceNow)
            ? Carbon::instance($periodEnd)
            : $referenceNow->copy();

        if ($effEnd->lessThanOrEqualTo($effStart)) {
            return $this->emptyResult();
        }

        $totalSeconds = (int) $effStart->diffInSeconds($effEnd);

        $histories = $equipment->relationLoaded('statusHistories')
            ? $equipment->statusHistories->sortBy([['changed_at', 'asc'], ['id', 'asc']])->values()
            : $equipment->statusHistories()->reorder()->orderBy('changed_at')->orderBy('id')->get();

        // Determine the equipment's status right at effStart.
        $lastBeforeOrAtStart = $histories
            ->filter(fn ($h) => $h->changed_at !== null && $h->changed_at->lessThanOrEqualTo($effStart))
            ->last();

        if ($lastBeforeOrAtStart) {
            $currentStatus = $lastBeforeOrAtStart->to_status;
        } else {
            $firstAfterStart = $histories
                ->first(fn ($h) => $h->changed_at !== null && $h->changed_at->greaterThan($effStart));

            if ($firstAfterStart) {
                $currentStatus = $firstAfterStart->from_status ?? $firstAfterStart->to_status;
            } else {
                $currentStatus = $equipment->status;
            }
        }

        $downtimeSeconds = 0;
        $cursor = $effStart->copy();

        $eventsInWindow = $histories->filter(
            fn ($h) => $h->changed_at !== null
                && $h->changed_at->greaterThan($effStart)
                && $h->changed_at->lessThan($effEnd),
        );

        foreach ($eventsInWindow as $event) {
            $eventTime = Carbon::instance($event->changed_at);

            if ($this->isDowntime($currentStatus)) {
                $downtimeSeconds += (int) $cursor->diffInSeconds($eventTime);
            }

            $cursor = $eventTime;
            $currentStatus = $event->to_status;
        }

        if ($this->isDowntime($currentStatus)) {
            $downtimeSeconds += (int) $cursor->diffInSeconds($effEnd);
        }

        $downtimeSeconds = min($downtimeSeconds, $totalSeconds);
        $uptimePercent = round((($totalSeconds - $downtimeSeconds) / $totalSeconds) * 100, 2);

        return [
            'total_seconds' => $totalSeconds,
            'downtime_seconds' => $downtimeSeconds,
            'total_hours' => round($totalSeconds / 3600, 2),
            'downtime_hours' => round($downtimeSeconds / 3600, 2),
            'uptime_percent' => $uptimePercent,
        ];
    }

    /**
     * Aggregate uptime across multiple equipment: U = (ΣT − ΣD) / ΣT × 100%.
     *
     * @param  Collection<int, Equipment>  $equipmentList
     * @return array{
     *     total_seconds: int,
     *     downtime_seconds: int,
     *     total_hours: float,
     *     downtime_hours: float,
     *     uptime_percent: float|null,
     *     equipment_count: int
     * }
     */
    public function forCollection(
        Collection $equipmentList,
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd,
        ?CarbonInterface $now = null,
    ): array {
        $sumT = 0;
        $sumD = 0;
        $counted = 0;

        foreach ($equipmentList as $equipment) {
            if (! $equipment->is_active) {
                continue;
            }

            $metrics = $this->forEquipment($equipment, $periodStart, $periodEnd, $now);

            if ($metrics['total_seconds'] > 0) {
                $sumT += $metrics['total_seconds'];
                $sumD += $metrics['downtime_seconds'];
                $counted++;
            }
        }

        $uptimePercent = $sumT > 0 ? round((($sumT - $sumD) / $sumT) * 100, 2) : null;

        return [
            'total_seconds' => $sumT,
            'downtime_seconds' => $sumD,
            'total_hours' => round($sumT / 3600, 2),
            'downtime_hours' => round($sumD / 3600, 2),
            'uptime_percent' => $uptimePercent,
            'equipment_count' => $counted,
        ];
    }

    /**
     * Monthly uptime series for the last N months (oldest to newest) for dashboard charts.
     *
     * @return array<int, array{month: string, label: string, uptime_percent: float|null, total_hours: float, downtime_hours: float}>
     */
    public function monthlySeries(int $months = 12, ?int $roomId = null, ?CarbonInterface $now = null): array
    {
        $referenceNow = $now ? Carbon::instance($now) : now();

        $equipmentList = Equipment::query()
            ->where('is_active', true)
            ->when($roomId, fn (Builder $q) => $q->where('room_id', $roomId))
            ->with('statusHistories')
            ->get();

        $series = [];

        for ($offset = $months - 1; $offset >= 0; $offset--) {
            $monthDate = $referenceNow->copy()->startOfMonth()->subMonthsNoOverflow($offset);
            $start = $monthDate->copy()->startOfMonth();
            $end = $monthDate->copy()->endOfMonth();

            $agg = $this->forCollection($equipmentList, $start, $end, $referenceNow);

            $series[] = [
                'month' => $monthDate->format('Y-m'),
                'label' => $monthDate->translatedFormat('M Y'),
                'uptime_percent' => $agg['uptime_percent'],
                'total_hours' => $agg['total_hours'],
                'downtime_hours' => $agg['downtime_hours'],
            ];
        }

        return $series;
    }

    private function isDowntime(?EquipmentStatus $status): bool
    {
        return $status !== null && in_array($status, self::DOWNTIME_STATUSES, true);
    }

    /**
     * @return array{total_seconds: int, downtime_seconds: int, total_hours: float, downtime_hours: float, uptime_percent: float|null}
     */
    private function emptyResult(): array
    {
        return [
            'total_seconds' => 0,
            'downtime_seconds' => 0,
            'total_hours' => 0.0,
            'downtime_hours' => 0.0,
            'uptime_percent' => null,
        ];
    }
}
