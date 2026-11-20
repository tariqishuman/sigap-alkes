<?php

namespace App\Services;

use App\Enums\CalibrationResult;
use App\Enums\DamageReportStatus;
use App\Enums\EquipmentStatus;
use App\Filament\Resources\Equipment\EquipmentResource;
use App\Models\Calibration;
use App\Models\DamageReport;
use App\Models\Equipment;
use App\Models\Repair;
use App\Models\Room;
use App\Models\SparePart;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class DashboardMetricsService
{
    private const CACHE_TTL_SECONDS = 600; // 10 minutes per filter combo

    public function __construct(
        private readonly ComplianceCalculator $complianceCalculator,
        private readonly UptimeCalculator $uptimeCalculator,
        private readonly DepreciationCalculator $depreciationCalculator,
        private readonly SettingsService $settings,
    ) {}

    /**
     * Resolve current and previous period windows from filter state.
     *
     * @return array{
     *     period: string,
     *     start: Carbon,
     *     end: Carbon,
     *     prev_start: Carbon,
     *     prev_end: Carbon,
     *     label: string,
     *     as_of: Carbon
     * }
     */
    public function resolvePeriod(string $period = 'monthly', ?string $date = null): array
    {
        $anchor = filled($date) ? Carbon::parse($date) : now();

        return match ($period) {
            'daily' => [
                'period' => 'daily',
                'start' => $anchor->copy()->startOfDay(),
                'end' => $anchor->copy()->endOfDay(),
                'prev_start' => $anchor->copy()->subDay()->startOfDay(),
                'prev_end' => $anchor->copy()->subDay()->endOfDay(),
                'label' => $anchor->translatedFormat('j M Y'),
                'as_of' => $anchor->copy()->endOfDay(),
            ],
            'weekly' => [
                'period' => 'weekly',
                'start' => $anchor->copy()->subDays(6)->startOfDay(),
                'end' => $anchor->copy()->endOfDay(),
                'prev_start' => $anchor->copy()->subDays(13)->startOfDay(),
                'prev_end' => $anchor->copy()->subDays(7)->endOfDay(),
                'label' => $anchor->copy()->subDays(6)->translatedFormat('j M').' – '.$anchor->translatedFormat('j M Y'),
                'as_of' => $anchor->copy()->endOfDay(),
            ],
            default => [
                'period' => 'monthly',
                'start' => $anchor->copy()->startOfMonth(),
                'end' => $anchor->copy()->endOfMonth(),
                'prev_start' => $anchor->copy()->startOfMonth()->subMonthNoOverflow()->startOfMonth(),
                'prev_end' => $anchor->copy()->startOfMonth()->subMonthNoOverflow()->endOfMonth(),
                'label' => $anchor->copy()->startOfMonth()->translatedFormat('j').' – '.$anchor->copy()->endOfMonth()->translatedFormat('j M Y'),
                'as_of' => $anchor->copy()->endOfMonth()->min(now()),
            ],
        };
    }

    /**
     * All top-row executive KPIs (Total Alat, Kepatuhan, Uptime Gauge, Lama Perbaikan, Terlambat).
     * Cached 10 minutes per filter combination.
     *
     * @return array<string, mixed>
     */
    public function executiveTopMetrics(string $period = 'monthly', ?string $date = null, ?int $roomId = null): array
    {
        $window = $this->resolvePeriod($period, $date);
        $cacheKey = $this->cacheKey('exec_top', [
            'p' => $window['period'],
            'd' => $window['start']->format('Y-m-d'),
            'r' => $roomId ?? 0,
            't' => $this->settings->uptimeTargetPercent(),
        ]);

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($window, $roomId): array {
            $activeEquipment = Equipment::query()
                ->where('is_active', true)
                ->when($roomId, fn (Builder $q) => $q->where('room_id', $roomId))
                ->with([
                    'room',
                    'equipmentType',
                    'statusHistories',
                    'latestValidCalibration',
                    'calibrations' => fn ($q) => $q->where('result', CalibrationResult::Pass),
                ])
                ->get();

            // 1. Total Alat Aktif + 6-status counts
            $statusCounts = [];
            foreach (EquipmentStatus::cases() as $status) {
                $statusCounts[$status->value] = $activeEquipment->where('status', $status)->count();
            }

            $roomCount = $activeEquipment->pluck('room_id')->filter()->unique()->count();

            // 2. Kepatuhan Kalibrasi + sparkline 12 bulan + delta vs bulan lalu
            $complianceSeries = $this->complianceCalculator->monthlySeries(12, $roomId, $window['as_of']);
            $currentCompliance = $this->complianceCalculator->calculate($roomId, $window['as_of']);
            $prevPoint = $complianceSeries[count($complianceSeries) - 2] ?? null;
            $complianceDelta = ($currentCompliance['compliance_percent'] !== null && ($prevPoint['compliance_percent'] ?? null) !== null)
                ? round($currentCompliance['compliance_percent'] - $prevPoint['compliance_percent'], 1)
                : null;

            $asOfDate = $window['as_of']->copy()->startOfDay();
            $due30Days = $activeEquipment->filter(function (Equipment $eq) use ($asOfDate): bool {
                $validUntil = $eq->latestValidCalibration?->valid_until;
                if ($validUntil === null) {
                    return false;
                }
                $due = $validUntil->copy()->startOfDay();

                return $due->greaterThanOrEqualTo($asOfDate) && $due->lessThanOrEqualTo($asOfDate->copy()->addDays(30));
            })->count();

            // 3. Uptime Bulan Ini (gauge) + bulan lalu + ruangan terendah
            $nowRef = $window['as_of'];
            $uptimeNow = $this->uptimeCalculator->forCollection($activeEquipment, $window['start'], $window['end'], $nowRef);
            $uptimePrev = $this->uptimeCalculator->forCollection($activeEquipment, $window['prev_start'], $window['prev_end'], $nowRef);
            $targetPercent = $this->settings->uptimeTargetPercent();

            $lowestRoom = $this->findLowestUptimeRoom($activeEquipment, $window['start'], $window['end'], $nowRef);

            // 4. Rata-rata Lama Perbaikan (repairs.finished_at - damage_reports.reported_at)
            $repairStats = $this->computeRepairDurationStats($window['start'], $window['end'], $window['prev_start'], $window['prev_end'], $roomId);

            // 5. Terlambat Kalibrasi + sparkline 12 bulan dari status_histories + delta vs bulan lalu
            $overdueStats = $this->computeOverdueStats($activeEquipment, $window['as_of']);

            return [
                'period_label' => $window['label'],
                'total_equipment' => [
                    'count' => $activeEquipment->count(),
                    'room_count' => $roomCount,
                    'status_counts' => $statusCounts,
                ],
                'compliance' => [
                    'percent' => $currentCompliance['compliance_percent'],
                    'formatted' => $currentCompliance['compliance_percent'] !== null
                        ? number_format($currentCompliance['compliance_percent'], 0, ',', '.').'%'
                        : '–',
                    'delta' => $complianceDelta,
                    'delta_label' => $complianceDelta !== null ? number_format(abs($complianceDelta), 1, ',', '.').'%' : null,
                    'required_count' => $currentCompliance['required_count'],
                    'compliant_count' => $currentCompliance['compliant_count'],
                    'due_30_days' => $due30Days,
                    'sparkline' => array_map(fn (array $p): float => (float) ($p['compliance_percent'] ?? 0), $complianceSeries),
                ],
                'uptime' => [
                    'percent' => $uptimeNow['uptime_percent'],
                    'total_hours' => $uptimeNow['total_hours'],
                    'downtime_hours' => $uptimeNow['downtime_hours'],
                    'target_percent' => $targetPercent,
                    'previous_percent' => $uptimePrev['uptime_percent'],
                    'lowest_room' => $lowestRoom,
                ],
                'repair_duration' => $repairStats,
                'overdue' => $overdueStats,
            ];
        });
    }

    /**
     * Damages breakdown by equipment type or room (resolved vs open) for Row 3 chart.
     *
     * @return array<string, mixed>
     */
    public function damagesBreakdown(string $period = 'monthly', ?string $date = null, ?int $roomId = null, string $groupBy = 'type'): array
    {
        $window = $this->resolvePeriod($period, $date);
        $groupKey = $groupBy === 'room' ? 'room' : 'type';
        $cacheKey = $this->cacheKey('damages', [
            'p' => $window['period'],
            'd' => $window['start']->format('Y-m-d'),
            'r' => $roomId ?? 0,
            'g' => $groupKey,
        ]);

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($roomId, $groupKey): array {
            $reports = DamageReport::query()
                ->whereHas('equipment', function (Builder $q) use ($roomId): void {
                    $q->where('is_active', true)
                        ->when($roomId, fn (Builder $rq) => $rq->where('room_id', $roomId));
                })
                ->with(['equipment.equipmentType', 'equipment.room'])
                ->get();

            $byType = $reports->groupBy(fn (DamageReport $r): string => $r->equipment?->equipmentType?->name ?? 'Lainnya');
            $byRoom = $reports->groupBy(fn (DamageReport $r): string => $r->equipment?->room?->name ?? 'Lainnya');

            $mostDamagedType = $byType->sortByDesc->count()->keys()->first() ?? '–';
            $topRoom = $byRoom->sortByDesc->count()->keys()->first() ?? '–';
            $openCount = $reports->filter(fn (DamageReport $r): bool => $r->status !== DamageReportStatus::Resolved)->count();

            $grouped = ($groupKey === 'room' ? $byRoom : $byType)
                ->map(function (Collection $items, string $label): array {
                    $resolved = $items->where('status', DamageReportStatus::Resolved)->count();
                    $open = $items->count() - $resolved;

                    return [
                        'label' => $label,
                        'resolved' => $resolved,
                        'open' => $open,
                        'total' => $items->count(),
                    ];
                })
                ->sortByDesc('total')
                ->take(7)
                ->values()
                ->all();

            return [
                'group_by' => $groupKey,
                'most_damaged_type' => $mostDamagedType,
                'open_reports_count' => $openCount,
                'top_room' => $topRoom,
                'items' => $grouped,
            ];
        });
    }

    /**
     * Upcoming 6 calibrations (overdue first, then soonest due) for Row 3 right card.
     *
     * @return array<int, array<string, mixed>>
     */
    public function upcomingCalibrations(?int $roomId = null, int $limit = 6): array
    {
        $cacheKey = $this->cacheKey('upcoming_cal', ['r' => $roomId ?? 0, 'l' => $limit]);

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($roomId, $limit): array {
            $today = today();
            $cutoff = $today->copy()->addDays(45);

            return Equipment::query()
                ->where('is_active', true)
                ->when($roomId, fn (Builder $q) => $q->where('room_id', $roomId))
                ->with(['room', 'latestValidCalibration'])
                ->get()
                ->filter(function (Equipment $eq) use ($cutoff): bool {
                    $due = $eq->latestValidCalibration?->valid_until;

                    return $due !== null && $due->copy()->startOfDay()->lessThanOrEqualTo($cutoff);
                })
                ->sortBy(fn (Equipment $eq) => $eq->latestValidCalibration->valid_until)
                ->take($limit)
                ->values()
                ->map(function (Equipment $eq) use ($today): array {
                    $due = $eq->latestValidCalibration->valid_until->copy()->startOfDay();
                    $daysDiff = (int) $today->diffInDays($due, false);

                    if ($daysDiff < 0) {
                        $abs = abs($daysDiff);
                        $chip = "Lewat {$abs} hr";
                        $tone = 'danger';
                    } elseif ($daysDiff === 0) {
                        $chip = 'Hari ini';
                        $tone = 'danger';
                    } else {
                        $chip = "{$daysDiff} hari";
                        $tone = 'warning';
                    }

                    return [
                        'id' => $eq->id,
                        'title' => $eq->name,
                        'subtitle' => trim("{$eq->inventory_code} · ".($eq->room?->name ?? '–')),
                        'chip' => $chip,
                        'tone' => $tone,
                        'url' => EquipmentResource::getUrl('view', ['record' => $eq]),
                    ];
                })
                ->all();
        });
    }

    /**
     * 12-month uptime series + auto Y-axis bounds + target line for Row 4 left chart.
     *
     * @return array<string, mixed>
     */
    public function uptimeTrend(int $months = 12, ?int $roomId = null): array
    {
        $target = $this->settings->uptimeTargetPercent();
        $cacheKey = $this->cacheKey('uptime_trend', ['m' => $months, 'r' => $roomId ?? 0, 't' => $target]);

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($months, $roomId, $target): array {
            $series = $this->uptimeCalculator->monthlySeries($months, $roomId);

            $labels = array_map(function (array $p): string {
                return Carbon::createFromFormat('Y-m', $p['month'])->translatedFormat('M');
            }, $series);

            $values = array_map(fn (array $p): ?float => $p['uptime_percent'], $series);
            $numeric = array_values(array_filter($values, fn ($v): bool => $v !== null));
            $minData = ! empty($numeric) ? min(min($numeric), (float) $target) : (float) $target;
            $yMin = (int) max(0, min(94, floor(($minData - 2) / 2) * 2));

            return [
                'labels' => $labels,
                'values' => $values,
                'target_percent' => $target,
                'y_min' => $yMin,
            ];
        });
    }

    /**
     * Book value by room (horizontal bars) for Row 4 right card.
     * Only called for roles with cost/asset access.
     *
     * @return array<string, mixed>
     */
    public function bookValueByRoom(?int $roomId = null): array
    {
        $cacheKey = $this->cacheKey('book_value_room', ['r' => $roomId ?? 0]);

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($roomId): array {
            $rooms = Room::query()
                ->when($roomId, fn (Builder $q) => $q->whereKey($roomId))
                ->with(['equipment' => fn ($q) => $q->where('is_active', true)->with('equipmentType')])
                ->get();

            $rows = [];
            $grandTotal = 0.0;

            foreach ($rooms as $room) {
                $roomTotal = 0.0;
                foreach ($room->equipment as $eq) {
                    $roomTotal += (float) $this->depreciationCalculator->calculateForEquipment($eq)['book_value'];
                }

                if ($roomTotal > 0 || $roomId !== null) {
                    $rows[] = [
                        'room_id' => $room->id,
                        'name' => $room->name,
                        'value' => $roomTotal,
                        'formatted' => self::formatCompactRupiah($roomTotal),
                    ];
                    $grandTotal += $roomTotal;
                }
            }

            usort($rows, fn (array $a, array $b): int => $b['value'] <=> $a['value']);
            $maxValue = ! empty($rows) ? max(1.0, (float) $rows[0]['value']) : 1.0;

            $rows = array_map(function (array $row) use ($maxValue): array {
                $row['percent'] = round(($row['value'] / $maxValue) * 100, 1);

                return $row;
            }, array_slice($rows, 0, 6));

            return [
                'total' => $grandTotal,
                'total_formatted' => self::formatCompactRupiah($grandTotal),
                'rooms' => $rows,
            ];
        });
    }

    /**
     * Role dashboard: Teknisi ("Tugas Anda", zero cost/asset data).
     *
     * @return array<string, mixed>
     */
    public function technicianSummary(int $userId): array
    {
        $cacheKey = $this->cacheKey('role_teknisi', ['u' => $userId]);

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($userId): array {
            $today = today();

            $cutoff14 = $today->copy()->addDays(14);
            $due14Collection = Equipment::query()
                ->where('is_active', true)
                ->with(['room', 'latestValidCalibration'])
                ->get()
                ->filter(function (Equipment $eq) use ($cutoff14): bool {
                    $due = $eq->latestValidCalibration?->valid_until;

                    return $due !== null && $due->copy()->startOfDay()->lessThanOrEqualTo($cutoff14);
                })
                ->sortBy(fn (Equipment $eq) => $eq->latestValidCalibration->valid_until)
                ->values();

            $due14 = $due14Collection
                ->take(6)
                ->map(function (Equipment $eq) use ($today): array {
                    $diff = (int) $today->diffInDays($eq->latestValidCalibration->valid_until->copy()->startOfDay(), false);

                    return [
                        'title' => $eq->name,
                        'subtitle' => trim("{$eq->inventory_code} · ".($eq->room?->name ?? '–')),
                        'chip' => $diff < 0 ? 'Lewat '.abs($diff).' hr' : ($diff === 0 ? 'Hari ini' : "{$diff} hari"),
                        'tone' => $diff <= 0 ? 'danger' : 'warning',
                        'url' => EquipmentResource::getUrl('view', ['record' => $eq]),
                    ];
                })
                ->all();

            $activeRepairs = Repair::query()
                ->where('technician_id', $userId)
                ->whereNull('finished_at')
                ->with(['damageReport.equipment.room'])
                ->latest('started_at')
                ->limit(6)
                ->get()
                ->map(fn (Repair $r): array => [
                    'title' => $r->damageReport?->equipment?->name ?? 'Alat',
                    'subtitle' => trim(($r->damageReport?->equipment?->inventory_code ?? '–').' · '.($r->damageReport?->equipment?->room?->name ?? '–')),
                    'chip' => 'Berjalan',
                    'tone' => 'info',
                ])
                ->all();

            $openReports = DamageReport::query()
                ->whereIn('status', [DamageReportStatus::Open, DamageReportStatus::InProgress])
                ->with(['equipment.room'])
                ->latest('reported_at')
                ->limit(6)
                ->get()
                ->map(fn (DamageReport $dr): array => [
                    'title' => $dr->equipment?->name ?? 'Alat',
                    'subtitle' => trim(($dr->equipment?->room?->name ?? '–').' · '.str($dr->complaint)->limit(45)),
                    'chip' => $dr->status->getLabel(),
                    'tone' => $dr->status === DamageReportStatus::Open ? 'danger' : 'warning',
                ])
                ->all();

            $lowStock = SparePart::query()
                ->whereColumn('stock', '<=', 'min_stock')
                ->orderBy('stock')
                ->limit(6)
                ->get()
                ->map(fn (SparePart $sp): array => [
                    'title' => $sp->name,
                    'subtitle' => "Batas minimum: {$sp->min_stock} {$sp->unit}",
                    'chip' => "{$sp->stock} {$sp->unit}",
                    'tone' => $sp->stock === 0 ? 'danger' : 'warning',
                ])
                ->all();

            return [
                'due_14_count' => $due14Collection->count(),
                'active_repairs_count' => Repair::query()->where('technician_id', $userId)->whereNull('finished_at')->count(),
                'open_reports_count' => DamageReport::query()->whereIn('status', [DamageReportStatus::Open, DamageReportStatus::InProgress])->count(),
                'low_stock_count' => SparePart::query()->whereColumn('stock', '<=', 'min_stock')->count(),
                'due_14_items' => $due14,
                'active_repair_items' => $activeRepairs,
                'open_report_items' => $openReports,
                'low_stock_items' => $lowStock,
            ];
        });
    }

    /**
     * Role dashboard: Kepala Ruangan (room-scoped, zero cost/asset data).
     *
     * @return array<string, mixed>
     */
    public function headNurseSummary(?int $roomId, int $userId): array
    {
        $cacheKey = $this->cacheKey('role_karu', ['r' => $roomId ?? 0, 'u' => $userId]);

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($roomId, $userId): array {
            $room = $roomId ? Room::find($roomId) : null;

            $equipment = Equipment::query()
                ->where('is_active', true)
                ->when($roomId, fn (Builder $q) => $q->where('room_id', $roomId))
                ->get();

            $statusCounts = [];
            foreach (EquipmentStatus::cases() as $status) {
                $statusCounts[$status->value] = $equipment->where('status', $status)->count();
            }

            $myReports = DamageReport::query()
                ->where('reported_by', $userId)
                ->with('equipment')
                ->latest('reported_at')
                ->limit(6)
                ->get()
                ->map(fn (DamageReport $dr): array => [
                    'title' => $dr->equipment?->name ?? 'Alat',
                    'subtitle' => ($dr->reported_at?->translatedFormat('d M Y') ?? '–').' · '.str($dr->complaint)->limit(40),
                    'chip' => $dr->status->getLabel(),
                    'tone' => match ($dr->status) {
                        DamageReportStatus::Open => 'danger',
                        DamageReportStatus::InProgress => 'warning',
                        DamageReportStatus::Resolved => 'success',
                    },
                ])
                ->all();

            return [
                'room_name' => $room?->name ?? 'Ruangan Anda',
                'total_equipment' => $equipment->count(),
                'status_counts' => $statusCounts,
                'upcoming_calibrations' => $roomId ? $this->upcomingCalibrations($roomId, 6) : [],
                'my_reports' => $myReports,
            ];
        });
    }

    /**
     * Role dashboard: Badan Kalibrasi (zero cost/asset data).
     *
     * @return array<string, mixed>
     */
    public function calibrationBodySummary(?int $institutionId): array
    {
        $cacheKey = $this->cacheKey('role_kalibrasi', ['i' => $institutionId ?? 0]);

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($institutionId): array {
            $today = today();

            $needsCalQuery = Equipment::query()
                ->where('is_active', true)
                ->whereIn('status', [EquipmentStatus::Overdue, EquipmentStatus::CalibrationDue, EquipmentStatus::UnderTesting]);

            $needsCalItems = (clone $needsCalQuery)
                ->with(['room', 'latestValidCalibration'])
                ->get()
                ->sortBy(fn (Equipment $eq) => $eq->latestValidCalibration?->valid_until ?? now()->addYears(10))
                ->take(6)
                ->values()
                ->map(function (Equipment $eq) use ($today): array {
                    $due = $eq->latestValidCalibration?->valid_until;
                    $diff = $due
                        ? (int) $today->diffInDays($due->copy()->startOfDay(), false)
                        : null;

                    return [
                        'title' => $eq->name,
                        'subtitle' => trim("{$eq->inventory_code} · ".($eq->room?->name ?? '–')),
                        'chip' => $diff === null
                            ? $eq->status->getLabel()
                            : ($diff < 0 ? 'Lewat '.abs($diff).' hr' : ($diff === 0 ? 'Hari ini' : "{$diff} hari")),
                        'tone' => $eq->status === EquipmentStatus::Overdue ? 'danger' : 'warning',
                        'url' => EquipmentResource::getUrl('view', ['record' => $eq]),
                    ];
                })
                ->all();

            $monthStart = now()->startOfMonth();
            $monthEnd = now()->endOfMonth();

            $monthCalQuery = Calibration::query()
                ->when($institutionId, fn (Builder $q) => $q->where('testing_institution_id', $institutionId))
                ->whereBetween('tested_at', [$monthStart, $monthEnd]);

            $recordedMonth = (clone $monthCalQuery)->count();
            $passedMonth = (clone $monthCalQuery)->where('result', CalibrationResult::Pass)->count();
            $failedMonth = (clone $monthCalQuery)->where('result', CalibrationResult::Fail)->count();

            return [
                'needs_calibration_count' => (clone $needsCalQuery)->count(),
                'overdue_count' => Equipment::query()->where('is_active', true)->where('status', EquipmentStatus::Overdue)->count(),
                'due_count' => Equipment::query()->where('is_active', true)->where('status', EquipmentStatus::CalibrationDue)->count(),
                'recorded_this_month' => $recordedMonth,
                'passed_this_month' => $passedMonth,
                'failed_this_month' => $failedMonth,
                'needs_calibration_items' => $needsCalItems,
            ];
        });
    }

    /**
     * Role dashboard: Magang (zero cost, asset value, or stock data).
     *
     * @return array<string, mixed>
     */
    public function internSummary(): array
    {
        $cacheKey = $this->cacheKey('role_magang', []);

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function (): array {
            $equipment = Equipment::query()->where('is_active', true)->get();

            $statusCounts = [];
            foreach (EquipmentStatus::cases() as $status) {
                $statusCounts[$status->value] = $equipment->where('status', $status)->count();
            }

            $openReports = DamageReport::query()
                ->whereIn('status', [DamageReportStatus::Open, DamageReportStatus::InProgress])
                ->with(['equipment.room'])
                ->latest('reported_at')
                ->limit(6)
                ->get()
                ->map(fn (DamageReport $dr): array => [
                    'title' => $dr->equipment?->name ?? 'Alat',
                    'subtitle' => trim(($dr->equipment?->room?->name ?? '–').' · '.str($dr->complaint)->limit(45)),
                    'chip' => $dr->status->getLabel(),
                    'tone' => $dr->status === DamageReportStatus::Open ? 'danger' : 'warning',
                ])
                ->all();

            return [
                'total_equipment' => $equipment->count(),
                'room_count' => $equipment->pluck('room_id')->filter()->unique()->count(),
                'status_counts' => $statusCounts,
                'upcoming_calibrations' => $this->upcomingCalibrations(null, 6),
                'open_reports' => $openReports,
            ];
        });
    }

    public static function formatCompactRupiah(float $amount): string
    {
        if ($amount >= 1_000_000_000) {
            return 'Rp'.number_format($amount / 1_000_000_000, 2, ',', '.').' M';
        }

        if ($amount >= 1_000_000) {
            return 'Rp'.number_format($amount / 1_000_000, 0, ',', '.').' jt';
        }

        if ($amount >= 1_000) {
            return 'Rp'.number_format($amount / 1_000, 0, ',', '.').' rb';
        }

        return 'Rp'.number_format($amount, 0, ',', '.');
    }

    /**
     * @param  Collection<int, Equipment>  $activeEquipment
     */
    private function findLowestUptimeRoom(Collection $activeEquipment, Carbon $start, Carbon $end, Carbon $nowRef): string
    {
        $byRoom = $activeEquipment->groupBy('room_id');
        $lowestName = '–';
        $lowestVal = 101.0;

        foreach ($byRoom as $items) {
            $roomName = $items->first()?->room?->name ?? '–';
            $u = $this->uptimeCalculator->forCollection($items, $start, $end, $nowRef)['uptime_percent'];

            if ($u !== null && $u < $lowestVal) {
                $lowestVal = $u;
                $lowestName = $roomName;
            }
        }

        return $lowestName;
    }

    /**
     * @return array{
     *     avg_days: float|null,
     *     formatted: string,
     *     delta: float|null,
     *     delta_label: string|null,
     *     finished_count: int,
     *     in_progress_count: int,
     *     longest_days: int|null
     * }
     */
    private function computeRepairDurationStats(Carbon $start, Carbon $end, Carbon $prevStart, Carbon $prevEnd, ?int $roomId): array
    {
        $baseQuery = Repair::query()
            ->whereHas('damageReport.equipment', function (Builder $q) use ($roomId): void {
                $q->where('is_active', true)
                    ->when($roomId, fn (Builder $rq) => $rq->where('room_id', $roomId));
            })
            ->with('damageReport');

        $finishedInPeriod = (clone $baseQuery)
            ->whereNotNull('finished_at')
            ->whereBetween('finished_at', [$start, $end])
            ->get();

        $finishedPrev = (clone $baseQuery)
            ->whereNotNull('finished_at')
            ->whereBetween('finished_at', [$prevStart, $prevEnd])
            ->get();

        $inProgressCount = (clone $baseQuery)
            ->whereNull('finished_at')
            ->count();

        $durations = $finishedInPeriod->map(function (Repair $r): ?float {
            $reportedAt = $r->damageReport?->reported_at ?? $r->started_at;
            if ($reportedAt === null || $r->finished_at === null) {
                return null;
            }

            return max(0.0, $reportedAt->diffInMinutes($r->finished_at) / 1440);
        })->filter(fn ($v): bool => $v !== null)->values();

        $prevDurations = $finishedPrev->map(function (Repair $r): ?float {
            $reportedAt = $r->damageReport?->reported_at ?? $r->started_at;
            if ($reportedAt === null || $r->finished_at === null) {
                return null;
            }

            return max(0.0, $reportedAt->diffInMinutes($r->finished_at) / 1440);
        })->filter(fn ($v): bool => $v !== null)->values();

        $avg = $durations->isNotEmpty() ? round((float) $durations->avg(), 1) : null;
        $prevAvg = $prevDurations->isNotEmpty() ? round((float) $prevDurations->avg(), 1) : null;
        $delta = ($avg !== null && $prevAvg !== null) ? round($avg - $prevAvg, 1) : null;
        $longest = $durations->isNotEmpty() ? (int) ceil((float) $durations->max()) : null;

        return [
            'avg_days' => $avg,
            'formatted' => $avg !== null ? number_format($avg, 1, ',', '.') : '–',
            'delta' => $delta,
            'delta_label' => $delta !== null ? number_format(abs($delta), 1, ',', '.').' hari' : null,
            'finished_count' => $finishedInPeriod->count(),
            'in_progress_count' => $inProgressCount,
            'longest_days' => $longest,
        ];
    }

    /**
     * Overdue count, aging buckets, and 12-month end-of-month overdue sparkline from status_histories.
     *
     * @param  Collection<int, Equipment>  $activeEquipment
     * @return array<string, mixed>
     */
    private function computeOverdueStats(Collection $activeEquipment, Carbon $asOf): array
    {
        $asOfDay = $asOf->copy()->startOfDay();
        $overdueNow = $activeEquipment->where('status', EquipmentStatus::Overdue);

        $lte7 = 0;
        $d8to30 = 0;
        $gt30 = 0;

        foreach ($overdueNow as $eq) {
            $validUntil = $eq->latestValidCalibration?->valid_until;
            $daysPast = $validUntil
                ? max(0, (int) $validUntil->copy()->startOfDay()->diffInDays($asOfDay, false))
                : 1;

            if ($daysPast <= 7) {
                $lte7++;
            } elseif ($daysPast <= 30) {
                $d8to30++;
            } else {
                $gt30++;
            }
        }

        // Compute historical overdue count at end of each of the last 12 months from passed calibration certificates:
        // active equipment requiring calibration whose latest passed certificate (tested_at <= pointEnd) has valid_until < pointDay.
        $calibrationRequired = $activeEquipment->filter(fn (Equipment $eq): bool => (bool) $eq->equipmentType?->requires_calibration);

        $sparkline = [];
        for ($offset = 11; $offset >= 0; $offset--) {
            if ($offset === 0) {
                $sparkline[] = $overdueNow->count();

                continue;
            }

            $pointDay = $asOf->copy()->startOfMonth()->subMonthsNoOverflow($offset)->endOfMonth()->startOfDay();
            $pointEnd = $pointDay->copy()->endOfDay();

            $countAtPoint = 0;
            foreach ($calibrationRequired as $eq) {
                if ($eq->created_at !== null && $eq->created_at->greaterThan($pointEnd)) {
                    continue;
                }

                $latestAtPoint = $eq->calibrations
                    ->filter(fn ($cal) => $cal->tested_at !== null && $cal->tested_at->copy()->startOfDay()->lessThanOrEqualTo($pointEnd))
                    ->sortByDesc(fn ($cal) => $cal->tested_at->timestamp)
                    ->first();

                if ($latestAtPoint && $latestAtPoint->valid_until !== null && $latestAtPoint->valid_until->copy()->startOfDay()->lessThan($pointDay)) {
                    $countAtPoint++;
                }
            }

            $sparkline[] = $countAtPoint;
        }

        $currentCount = $overdueNow->count();
        $prevMonthCount = $sparkline[count($sparkline) - 2] ?? null;
        $delta = $prevMonthCount !== null ? $currentCount - $prevMonthCount : null;

        return [
            'count' => $currentCount,
            'delta' => $delta,
            'delta_label' => $delta !== null ? abs($delta).' alat' : null,
            'lte_7' => $lte7,
            'days_8_30' => $d8to30,
            'gt_30' => $gt30,
            'sparkline' => $sparkline,
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function cacheKey(string $section, array $params): string
    {
        return 'sigap.dashboard.v2.'.$section.'.'.md5(json_encode($params));
    }
}
