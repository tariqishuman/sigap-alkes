<?php

namespace App\Filament\Resources\Equipment\Pages;

use App\Filament\Resources\Equipment\EquipmentResource;
use App\Filament\Resources\Equipment\Widgets\EquipmentStatusBarWidget;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEquipment extends ListRecords
{
    protected static string $resource = EquipmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah alat'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            EquipmentStatusBarWidget::class,
        ];
    }
}
