<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Exports\AspakEquipmentExporter;
use App\Filament\Exports\OperationalReportExporter;
use App\Models\Equipment;
use App\Models\Room;
use App\Services\ComplianceCalculator;
use App\Services\UptimeCalculator;
use BackedEnum;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

class OperationalReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?string $navigationLabel = 'Uptime & Kepatuhan';

    protected static ?string $title = 'Laporan Uptime & Kepatuhan Kalibrasi';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'laporan-operasional';

    protected string $view = 'filament.pages.operational-report';

    public string $selectedMonth = '';

    public ?string $selectedRoomId = null;

    public static function canAccess(): bool
    {
        return Auth::user()?->hasAnyRole([
            UserRole::SuperAdmin->value,
            UserRole::AdminIpsrs->value,
            UserRole::Manajemen->value,
        ]) ?? false;
    }

    public function mount(): void
    {
        $this->selectedMonth = today()->format('Y-m');
    }

    public function updatedSelectedMonth(): void
    {
        $this->resetTable();
    }

    public function updatedSelectedRoomId(): void
    {
        $this->resetTable();
    }

    /**
     * @return array<string, string>
     */
    public function getMonthOptions(): array
    {
        $options = [];

        for ($offset = 0; $offset >= -11; $offset--) {
            $month = today()->startOfMonth()->addMonthsNoOverflow($offset);
            $options[$month->format('Y-m')] = $month->translatedFormat('F Y');
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    public function getRoomOptions(): array
    {
        return Room::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function getSelectedPeriod(): array
    {
        $monthStr = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->selectedMonth)
            ? $this->selectedMonth
            : today()->format('Y-m');

        $month = Carbon::createFromFormat('!Y-m', $monthStr);

        return [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()];
    }

    /**
     * @return array{uptime: array<string, mixed>, compliance: array<string, mixed>, period_label: string, room_label: string}
     */
    public function getSummary(): array
    {
        [$start, $end] = $this->getSelectedPeriod();
        $roomId = filled($this->selectedRoomId) ? (int) $this->selectedRoomId : null;

        $equipmentList = $this->baseEquipmentQuery()->get();
        $uptime = app(UptimeCalculator::class)->forCollection($equipmentList, $start, $end);
        $compliance = app(ComplianceCalculator::class)->calculate($roomId);

        $roomLabel = $roomId
            ? (Room::whereKey($roomId)->value('name') ?? 'Semua Ruangan')
            : 'Semua Ruangan';

        return [
            'uptime' => $uptime,
            'compliance' => $compliance,
            'period_label' => $start->translatedFormat('F Y'),
            'room_label' => $roomLabel,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make('export_excel')
                ->label('Ekspor Excel')
                ->icon(Heroicon::OutlinedTableCells)
                ->exporter(OperationalReportExporter::class)
                ->modifyQueryUsing(fn (Builder $query) => $this->applyEquipmentFilters($query))
                ->options(fn (): array => ['month' => $this->selectedMonth]),
            Action::make('export_pdf')
                ->label('Ekspor PDF')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('gray')
                ->action(fn (): StreamedResponse => $this->downloadPdf()),
            ExportAction::make('export_aspak')
                ->label('Ekspor ASPAK (Sementara)')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('gray')
                ->modalHeading('Ekspor Format ASPAK (Kolom Sementara)')
                ->modalDescription('Format kolom ASPAK ini bersifat sementara dan dapat disesuaikan setelah observasi di RS mitra.')
                ->exporter(AspakEquipmentExporter::class)
                ->modifyQueryUsing(fn (Builder $query) => $this->applyEquipmentFilters($query)),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->baseEquipmentQuery())
            ->defaultSort('inventory_code')
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Alat')
                    ->weight('medium')
                    ->searchable(['name', 'inventory_code'])
                    ->sortable()
                    ->description(fn (Equipment $record): string => $record->inventory_code),
                TextColumn::make('room.name')
                    ->label('Ruangan'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('total_hours')
                    ->label('Total Jam Operasi')
                    ->alignEnd()
                    ->state(function (Equipment $record): string {
                        [$start, $end] = $this->getSelectedPeriod();
                        $metrics = app(UptimeCalculator::class)->forEquipment($record, $start, $end);

                        return number_format($metrics['total_hours'], 1, ',', '.').' jam';
                    }),
                TextColumn::make('downtime_hours')
                    ->label('Downtime')
                    ->alignEnd()
                    ->state(function (Equipment $record): string {
                        [$start, $end] = $this->getSelectedPeriod();
                        $metrics = app(UptimeCalculator::class)->forEquipment($record, $start, $end);

                        return number_format($metrics['downtime_hours'], 1, ',', '.').' jam';
                    }),
                TextColumn::make('uptime_percent')
                    ->label('Uptime')
                    ->weight('bold')
                    ->alignEnd()
                    ->state(function (Equipment $record): string {
                        [$start, $end] = $this->getSelectedPeriod();
                        $metrics = app(UptimeCalculator::class)->forEquipment($record, $start, $end);

                        return $metrics['uptime_percent'] !== null
                            ? number_format($metrics['uptime_percent'], 2, ',', '.').'%'
                            : '–';
                    }),
            ])
            ->emptyState(view('filament.tables.empty-state', [
                'image' => 'empty-report',
                'title' => 'Tidak ada data laporan',
                'description' => 'Tidak ada alat aktif yang sesuai dengan filter periode dan ruangan yang dipilih.',
            ]));
    }

    public function downloadPdf(): StreamedResponse
    {
        [$start, $end] = $this->getSelectedPeriod();
        $summary = $this->getSummary();
        $uptimeCalculator = app(UptimeCalculator::class);

        $rows = $this->baseEquipmentQuery()
            ->orderBy('inventory_code')
            ->get()
            ->map(function (Equipment $equipment) use ($uptimeCalculator, $start, $end): array {
                $metrics = $uptimeCalculator->forEquipment($equipment, $start, $end);

                return [
                    'equipment' => $equipment,
                    'metrics' => $metrics,
                ];
            });

        $pdfOutput = Pdf::loadView('pdf.operational-report', [
            'summary' => $summary,
            'rows' => $rows,
            'generatedAt' => now()->translatedFormat('d F Y H:i'),
        ])->setPaper('a4', 'landscape')->output();

        $filename = "laporan-operasional-{$start->format('Y-m')}.pdf";

        return response()->streamDownload(
            function () use ($pdfOutput): void {
                echo $pdfOutput;
            },
            $filename,
            ['Content-Type' => 'application/pdf'],
        );
    }

    private function baseEquipmentQuery(): Builder
    {
        return $this->applyEquipmentFilters(
            Equipment::query()->with(['room', 'equipmentType', 'statusHistories', 'latestValidCalibration'])
        );
    }

    private function applyEquipmentFilters(Builder $query): Builder
    {
        $query->where('is_active', true);

        if (filled($this->selectedRoomId)) {
            $query->where('room_id', (int) $this->selectedRoomId);
        }

        return $query;
    }
}
