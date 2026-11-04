<?php

namespace App\Filament\Pages;

use App\Enums\EquipmentStatus;
use App\Filament\Exports\CalibrationScheduleExporter;
use App\Filament\Resources\Equipment\EquipmentResource;
use App\Models\Equipment;
use BackedEnum;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class CalibrationSchedule extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Alat';

    protected static ?string $navigationLabel = 'Jadwal Kalibrasi';

    protected static ?string $title = 'Jadwal Kalibrasi';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'jadwal-kalibrasi';

    public static function canAccess(): bool
    {
        return Auth::user()?->can('ViewAny:Calibration') ?? false;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
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
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
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
                        // Filter state comes from the browser, so check its format before parsing.
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
            ]);
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
