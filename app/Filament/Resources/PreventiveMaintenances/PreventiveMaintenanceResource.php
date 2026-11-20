<?php

namespace App\Filament\Resources\PreventiveMaintenances;

use App\Enums\UserRole;
use App\Filament\Resources\PreventiveMaintenances\Pages\CreatePreventiveMaintenance;
use App\Filament\Resources\PreventiveMaintenances\Pages\ListPreventiveMaintenances;
use App\Filament\Resources\PreventiveMaintenances\Schemas\PreventiveMaintenanceForm;
use App\Filament\Resources\PreventiveMaintenances\Tables\PreventiveMaintenancesTable;
use App\Models\PreventiveMaintenance;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PreventiveMaintenanceResource extends Resource
{
    protected static ?string $model = PreventiveMaintenance::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $modelLabel = 'Pemeliharaan Preventif';

    protected static ?string $pluralModelLabel = 'Pemeliharaan Preventif';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['equipment.room', 'equipment.equipmentType', 'performedByUser']);
        $user = Auth::user();

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
            $query->whereHas('equipment', fn (Builder $q) => $q->where('room_id', $user->room_id ?? 0));
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return PreventiveMaintenanceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PreventiveMaintenancesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPreventiveMaintenances::route('/'),
            'create' => CreatePreventiveMaintenance::route('/create'),
        ];
    }
}
