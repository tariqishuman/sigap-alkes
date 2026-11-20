<?php

namespace App\Filament\Resources\PreventiveMaintenances\Pages;

use App\Filament\Resources\PreventiveMaintenances\PreventiveMaintenanceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPreventiveMaintenances extends ListRecords
{
    protected static string $resource = PreventiveMaintenanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Catat pemeliharaan'),
        ];
    }
}
