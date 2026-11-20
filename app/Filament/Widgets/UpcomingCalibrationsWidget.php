<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Filament\Resources\Equipment\EquipmentResource;
use App\Models\Equipment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class UpcomingCalibrationsWidget extends TableWidget
{
    protected static ?int $sort = 4;

    protected static ?string $heading = '10 Alat Jatuh Tempo Terdekat';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Auth::user()?->hasAnyRole([
            UserRole::SuperAdmin->value,
            UserRole::AdminIpsrs->value,
            UserRole::Manajemen->value,
        ]) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Equipment::query()
                    ->where('is_active', true)
                    ->whereHas('equipmentType', fn (Builder $q) => $q->where('requires_calibration', true))
                    ->with(['room', 'latestValidCalibration'])
                    ->addSelect(['equipment.*', 'due_date' => Equipment::dueDateQuery()])
                    ->orderByRaw('due_date IS NULL, due_date ASC')
                    ->limit(10)
            )
            ->paginated(false)
            ->recordUrl(fn (Equipment $record): ?string => EquipmentResource::canView($record)
                ? EquipmentResource::getUrl('view', ['record' => $record])
                : null)
            ->columns([
                TextColumn::make('inventory_code')
                    ->label('Kode Inventaris')
                    ->fontFamily('mono')
                    ->weight('medium'),
                TextColumn::make('name')
                    ->label('Nama Alat')
                    ->weight('medium'),
                TextColumn::make('room.name')
                    ->label('Ruangan'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('due_date')
                    ->label('Berlaku s.d.')
                    ->date('d M Y')
                    ->placeholder('Belum ada sertifikat')
                    ->alignEnd(),
            ]);
    }
}
