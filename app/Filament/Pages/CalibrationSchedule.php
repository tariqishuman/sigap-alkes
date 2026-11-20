<?php

namespace App\Filament\Pages;

use App\Enums\EquipmentStatus;
use App\Enums\UserRole;
use App\Filament\Exports\CalibrationScheduleExporter;
use App\Filament\Resources\Equipment\EquipmentResource;
use App\Filament\Resources\PreventiveMaintenances\PreventiveMaintenanceResource;
use App\Models\Equipment;
use BackedEnum;
use Filament\Actions\ExportAction;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class CalibrationSchedule extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::CalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Alat';

    protected static ?string $navigationLabel = 'Jadwal';

    protected static ?string $title = 'Jadwal Kalibrasi & Pemeliharaan';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'jadwal-kalibrasi';

    protected string $view = 'filament.pages.calibration-schedule';

    public string $viewMode = 'grouped';

    public string $activeScheduleTab = 'calibration';

    public static function getNavigationBadge(): ?string
    {
        $overdue = Equipment::query()
            ->where('is_active', true)
            ->where('status', EquipmentStatus::Overdue)
            ->count();

        return $overdue > 0 ? (string) $overdue : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->can('ViewAny:Calibration') ?? false;
    }

    public function canViewPreventiveMaintenanceTab(): bool
    {
        return Auth::user()?->can('ViewAny:PreventiveMaintenance') ?? false;
    }

    public function setActiveScheduleTab(string $tab): void
    {
        if ($tab === 'pm' && $this->canViewPreventiveMaintenanceTab()) {
            $this->activeScheduleTab = 'pm';

            return;
        }

        $this->activeScheduleTab = 'calibration';
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = $mode === 'table' ? 'table' : 'grouped';
    }

    /**
     * Group PM-required equipment into Terlambat -> Minggu ini -> Bulan ini -> Berikutnya.
     *
     * @return array<string, array{title: string, items: array<int, array<string, mixed>>}>
     */
    public function getGroupedPmSchedule(): array
    {
        $today = today();
        $weekEnd = $today->copy()->addDays(7);
        $monthEnd = $today->copy()->endOfMonth();
        $user = Auth::user();
        $canCreatePm = PreventiveMaintenanceResource::canCreate();

        $query = Equipment::query()
            ->where('is_active', true)
            ->whereHas('equipmentType', fn (Builder $q) => $q->whereNotNull('pm_interval_months')->where('pm_interval_months', '>', 0))
            ->with(['room', 'equipmentType', 'latestPreventiveMaintenance']);

        if (
            $user
            && $user->hasRole(UserRole::KepalaRuangan->value)
            && ! $user->hasAnyRole([
                UserRole::SuperAdmin->value,
                UserRole::AdminIpsrs->value,
                UserRole::Teknisi->value,
                UserRole::Manajemen->value,
                UserRole::Magang->value,
            ])
        ) {
            $query->where('room_id', $user->room_id ?? 0);
        }

        $equipment = $query->get()
            ->sortBy(fn (Equipment $eq) => $eq->latestPreventiveMaintenance?->next_due_at ?? now()->subYears(5));

        $groups = [
            'overdue' => ['title' => 'Terlambat', 'items' => []],
            'this_week' => ['title' => 'Minggu ini', 'items' => []],
            'this_month' => ['title' => 'Bulan ini', 'items' => []],
            'upcoming' => ['title' => 'Berikutnya', 'items' => []],
        ];

        foreach ($equipment as $eq) {
            $due = $eq->latestPreventiveMaintenance?->next_due_at?->copy()->startOfDay();
            $diff = $due ? (int) $today->diffInDays($due, false) : null;

            $item = [
                'title' => $eq->name,
                'subtitle' => trim("{$eq->inventory_code} · ".($eq->room?->name ?? '–').' · interval '.$eq->equipmentType->pm_interval_months.' bln'),
                'chip' => match (true) {
                    $diff === null => 'Belum pernah PM',
                    $diff < 0 => 'Lewat '.abs($diff).' hr',
                    $diff === 0 => 'Hari ini',
                    default => "{$diff} hari · ".$due->translatedFormat('d M Y'),
                },
                'tone' => match (true) {
                    $diff === null || $diff <= 0 => 'danger',
                    $diff <= 7 => 'warning',
                    default => 'success',
                },
                'url' => $canCreatePm
                    ? PreventiveMaintenanceResource::getUrl('create', ['equipment_id' => $eq->id])
                    : (EquipmentResource::canView($eq) ? EquipmentResource::getUrl('view', ['record' => $eq]) : null),
            ];

            if ($diff === null || $diff < 0) {
                $groups['overdue']['items'][] = $item;
            } elseif ($due->lessThanOrEqualTo($weekEnd)) {
                $groups['this_week']['items'][] = $item;
            } elseif ($due->lessThanOrEqualTo($monthEnd)) {
                $groups['this_month']['items'][] = $item;
            } else {
                $groups['upcoming']['items'][] = $item;
            }
        }

        return $groups;
    }

    /**
     * Group calibration-required equipment into Terlambat -> Minggu ini -> Bulan ini -> Berikutnya (§4.4).
     *
     * @return array<string, array{title: string, items: array<int, array<string, mixed>>}>
     */
    public function getGroupedSchedule(): array
    {
        $today = today();
        $weekEnd = $today->copy()->addDays(7);
        $monthEnd = $today->copy()->endOfMonth();

        $equipment = Equipment::query()
            ->where('is_active', true)
            ->whereHas('equipmentType', fn (Builder $q) => $q->where('requires_calibration', true))
            ->with(['room', 'equipmentType', 'latestValidCalibration'])
            ->get()
            ->sortBy(fn (Equipment $eq) => $eq->latestValidCalibration?->valid_until ?? now()->addYears(10));

        $groups = [
            'overdue' => ['title' => 'Terlambat', 'items' => []],
            'this_week' => ['title' => 'Minggu ini', 'items' => []],
            'this_month' => ['title' => 'Bulan ini', 'items' => []],
            'upcoming' => ['title' => 'Berikutnya', 'items' => []],
        ];

        foreach ($equipment as $eq) {
            $due = $eq->latestValidCalibration?->valid_until?->copy()->startOfDay();
            $diff = $due ? (int) $today->diffInDays($due, false) : null;

            $item = [
                'title' => $eq->name,
                'subtitle' => trim("{$eq->inventory_code} · ".($eq->room?->name ?? '–').' · '.($eq->equipmentType?->name ?? '–')),
                'chip' => match (true) {
                    $diff === null => 'Belum bersertifikat',
                    $diff < 0 => 'Lewat '.abs($diff).' hr',
                    $diff === 0 => 'Hari ini',
                    default => "{$diff} hari · ".$due->translatedFormat('d M Y'),
                },
                'tone' => match (true) {
                    $diff === null || $diff <= 0 => 'danger',
                    $diff <= 30 => 'warning',
                    default => 'success',
                },
                'url' => EquipmentResource::canView($eq)
                    ? EquipmentResource::getUrl('view', ['record' => $eq])
                    : null,
            ];

            if ($diff === null || $diff < 0) {
                $groups['overdue']['items'][] = $item;
            } elseif ($due->lessThanOrEqualTo($weekEnd)) {
                $groups['this_week']['items'][] = $item;
            } elseif ($due->lessThanOrEqualTo($monthEnd)) {
                $groups['this_month']['items'][] = $item;
            } else {
                $groups['upcoming']['items'][] = $item;
            }
        }

        return $groups;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Equipment::query()
                    ->where('is_active', true)
                    ->whereHas('equipmentType', fn (Builder $query) => $query->where('requires_calibration', true))
                    ->with(['room', 'equipmentType', 'latestValidCalibration'])
                    ->addSelect(['equipment.*', 'due_date' => Equipment::dueDateQuery()])
            )
            ->defaultSort('due_date')
            ->recordUrl(fn (Equipment $record): ?string => EquipmentResource::canView($record)
                ? EquipmentResource::getUrl('view', ['record' => $record])
                : null)
            ->columns([
                TextColumn::make('inventory_code')
                    ->label('Kode Inventaris')
                    ->fontFamily('mono')
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable(),
                TextColumn::make('room.name')
                    ->label('Ruangan'),
                TextColumn::make('equipmentType.name')
                    ->label('Jenis'),
                ViewColumn::make('status')
                    ->label('Status')
                    ->view('components.sigap.table-status-badge'),
                TextColumn::make('due_date')
                    ->label('Jatuh Tempo')
                    ->date('d M Y')
                    ->placeholder('Belum ada sertifikat')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('due_month')
                    ->label('Bulan jatuh tempo')
                    ->options(self::monthOptions())
                    ->query(function (Builder $query, array $data) {
                        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $data['value'])) {
                            return;
                        }

                        $month = Carbon::createFromFormat('!Y-m', $data['value']);
                        $query->dueBetween($month->copy()->startOfMonth(), $month->copy()->endOfMonth());
                    }),
                SelectFilter::make('room_id')
                    ->label('Ruangan')
                    ->relationship('room', 'name'),
                SelectFilter::make('equipment_type_id')
                    ->label('Jenis Alat')
                    ->relationship('equipmentType', 'name'),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(EquipmentStatus::class),
            ])
            ->headerActions([
                ExportAction::make()
                    ->label('Ekspor')
                    ->exporter(CalibrationScheduleExporter::class),
            ])
            ->emptyState(view('filament.tables.empty-state', [
                'image' => 'empty-calibration',
                'title' => 'Tidak ada jadwal kalibrasi',
                'description' => 'Tidak ditemukan alat wajib kalibrasi pada filter jadwal saat ini.',
            ]));
    }

    /**
     * @return array<string, string> Last 12 months through the next 24, for overdue and planned equipment
     */
    private static function monthOptions(): array
    {
        $options = [];

        foreach (range(-12, 24) as $offset) {
            $month = today()->startOfMonth()->addMonthsNoOverflow($offset);
            $options[$month->format('Y-m')] = $month->translatedFormat('F Y');
        }

        return $options;
    }
}
