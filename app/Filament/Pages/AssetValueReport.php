<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Exports\AspakEquipmentExporter;
use App\Models\Equipment;
use App\Services\DepreciationCalculator;
use BackedEnum;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

class AssetValueReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?string $navigationLabel = 'Nilai Aset';

    protected static ?string $title = 'Laporan Nilai Aset & Penyusutan';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'laporan-nilai-aset';

    public static function canAccess(): bool
    {
        return Auth::user()?->hasAnyRole([
            UserRole::SuperAdmin->value,
            UserRole::AdminIpsrs->value,
            UserRole::Manajemen->value,
        ]) ?? false;
    }

    public function getSubheading(): ?string
    {
        return 'Nilai buku adalah estimasi untuk kebutuhan manajemen, bukan pengganti pembukuan akuntansi resmi RS.';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_pdf')
                ->label('Ekspor PDF')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('gray')
                ->action(fn (): StreamedResponse => $this->downloadPdf()),
            ExportAction::make('export_aspak')
                ->label('Ekspor ASPAK (Sementara)')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->modalHeading('Ekspor Format ASPAK (Kolom Sementara)')
                ->modalDescription('Format kolom ASPAK ini bersifat sementara dan dapat disesuaikan setelah observasi di RS mitra.')
                ->exporter(AspakEquipmentExporter::class)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_active', true)),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Equipment::query()
                    ->where('is_active', true)
                    ->with(['room', 'equipmentType', 'repairs.repairSpareParts'])
            )
            ->defaultSort('inventory_code')
            ->columns([
                TextColumn::make('inventory_code')
                    ->label('Kode Inventaris')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nama Alat')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('room.name')
                    ->label('Ruangan'),
                TextColumn::make('acquisition_cost')
                    ->label('Harga Perolehan')
                    ->formatStateUsing(fn ($state): string => 'Rp'.number_format((float) $state, 0, ',', '.')),
                TextColumn::make('annual_depreciation')
                    ->label('Penyusutan / Th')
                    ->state(fn (Equipment $record): string => 'Rp'.number_format(
                        (float) app(DepreciationCalculator::class)->calculateForEquipment($record)['annual_depreciation'],
                        0,
                        ',',
                        '.',
                    )),
                TextColumn::make('book_value')
                    ->label('Nilai Buku')
                    ->weight('bold')
                    ->state(fn (Equipment $record): string => 'Rp'.number_format(
                        (float) app(DepreciationCalculator::class)->calculateForEquipment($record)['book_value'],
                        0,
                        ',',
                        '.',
                    )),
                TextColumn::make('total_repair_cost')
                    ->label('Total Perbaikan')
                    ->state(fn (Equipment $record): string => 'Rp'.number_format((float) $record->totalRepairCost(), 0, ',', '.')),
                TextColumn::make('replacement_flag')
                    ->label('Indikator')
                    ->badge()
                    ->color('warning')
                    ->state(fn (Equipment $record): ?string => app(DepreciationCalculator::class)->shouldConsiderReplacement($record)
                        ? 'Pertimbangkan ganti'
                        : null)
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('room_id')
                    ->label('Ruangan')
                    ->relationship('room', 'name'),
                SelectFilter::make('equipment_type_id')
                    ->label('Jenis Alat')
                    ->relationship('equipmentType', 'name'),
            ])
            ->emptyState(view('filament.tables.empty-state', [
                'image' => 'empty-report',
                'title' => 'Tidak ada data nilai aset',
                'description' => 'Tidak ada alat aktif yang sesuai dengan filter saat ini.',
            ]));
    }

    public function downloadPdf(): StreamedResponse
    {
        $calculator = app(DepreciationCalculator::class);
        $threshold = (int) config('sigap.replace_threshold_percent', 50);

        $equipmentList = Equipment::query()
            ->where('is_active', true)
            ->with(['room', 'equipmentType', 'repairs.repairSpareParts'])
            ->orderBy('inventory_code')
            ->get();

        $totalBookValue = '0.00';
        $totalRepairCost = '0.00';

        $rows = $equipmentList->map(function (Equipment $equipment) use ($calculator, $threshold, &$totalBookValue, &$totalRepairCost): array {
            $dep = $calculator->calculateForEquipment($equipment);
            $repair = $equipment->totalRepairCost();
            $shouldReplace = $calculator->meetsReplacementThreshold($repair, $dep['book_value'], $threshold);

            $totalBookValue = bcadd($totalBookValue, $dep['book_value'], 2);
            $totalRepairCost = bcadd($totalRepairCost, $repair, 2);

            return [
                'equipment' => $equipment,
                'depreciation' => $dep,
                'repair_cost' => $repair,
                'should_replace' => $shouldReplace,
            ];
        });

        $pdfOutput = Pdf::loadView('pdf.asset-value-report', [
            'rows' => $rows,
            'totalBookValue' => $totalBookValue,
            'totalRepairCost' => $totalRepairCost,
            'threshold' => $threshold,
            'generatedAt' => now()->translatedFormat('d F Y H:i'),
        ])->setPaper('a4', 'landscape')->output();

        return response()->streamDownload(
            function () use ($pdfOutput): void {
                echo $pdfOutput;
            },
            'laporan-nilai-aset-'.today()->format('Y-m-d').'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }
}
