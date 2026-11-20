<?php

namespace App\Filament\Resources\Equipment\RelationManagers;

use App\Filament\Resources\PreventiveMaintenances\PreventiveMaintenanceResource;
use App\Filament\Resources\PreventiveMaintenances\Tables\PreventiveMaintenancesTable;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class PreventiveMaintenancesRelationManager extends RelationManager
{
    protected static string $relationship = 'preventiveMaintenances';

    protected static ?string $title = 'Pemeliharaan';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Auth::user()?->can('ViewAny:PreventiveMaintenance') ?? false;
    }

    public function table(Table $table): Table
    {
        return PreventiveMaintenancesTable::configure($table, forEquipmentRelation: true)
            ->headerActions([
                Action::make('record_pm')
                    ->label('Catat pemeliharaan')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->visible(fn (): bool => PreventiveMaintenanceResource::canCreate()
                        && ($this->getOwnerRecord()->is_active ?? false)
                        && (bool) ($this->getOwnerRecord()->equipmentType?->pm_interval_months))
                    ->url(fn (): string => PreventiveMaintenanceResource::getUrl('create', [
                        'equipment_id' => $this->getOwnerRecord()->getKey(),
                    ])),
            ]);
    }
}
